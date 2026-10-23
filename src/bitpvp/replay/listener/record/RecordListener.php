<?php

declare(strict_types=1);

namespace bitpvp\replay\listener\record;

use bitpvp\replay\action\type\actor\ActorDeathAction;
use bitpvp\replay\action\type\actor\ActorDespawnAction;
use bitpvp\replay\action\type\actor\ActorEventAction;
use bitpvp\replay\action\type\actor\ActorSetMetadataAction;
use bitpvp\replay\action\type\entity\EntityChangeSkinAction;
use bitpvp\replay\action\type\entity\EntityEquipAction;
use bitpvp\replay\action\type\entity\EntityEffectAction;
use bitpvp\replay\action\type\entity\EntityInventoryAction;
use bitpvp\replay\action\type\entity\EntityPlayEmoteAction;
use bitpvp\replay\action\type\player\PlayerAnimationAction;
use bitpvp\replay\action\type\player\PlayerChatAction;
use bitpvp\replay\action\type\world\BlockEventAction;
use bitpvp\replay\action\type\world\LevelEventAction;
use bitpvp\replay\action\type\world\SignChangeAction;
use bitpvp\replay\action\type\world\SetBlockAction;
use bitpvp\replay\listener\handler\RecordPacketHandler;
use bitpvp\replay\log\type\PlayerDeathEventLog;
use bitpvp\replay\recorder\Recorder;
use bitpvp\replay\utils\IlegalHandlingException;
use pocketmine\block\BaseSign;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\entity\Living;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\block\BlockGrowEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\block\BlockFormEvent;
use pocketmine\event\block\BlockMeltEvent;
use pocketmine\event\block\BlockDeathEvent;
use pocketmine\event\block\BlockExplodeEvent;
use pocketmine\event\block\LeavesDecayEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntityEffectAddEvent;
use pocketmine\event\entity\EntityEffectRemoveEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChangeSkinEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\event\world\ChunkUnloadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\EmotePacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\player\Player;
use pocketmine\world\World;

class RecordListener implements Listener {

    private RecordPacketHandler $packetHandler;

    public function __construct(
        private Recorder $recorder
    ) {
        $this->packetHandler = new RecordPacketHandler($this->recorder);
    }

    /** @priority MONITOR */
    public function onChat(PlayerChatEvent $event): void {
        if (!$this->recorder->canRecordEntity($player = $event->getPlayer())) {
            return;
        }
        $text = $event->getFormatter()->format($player->getDisplayName(), $event->getMessage());
        if ($text instanceof Translatable) {
            $text = $player->getLanguage()->translate($text);
        }
        $this->recorder->addAction(PlayerChatAction::create($player->getId(), (string)$text));
    }

    /** @priority MONITOR */
    public function onMove(PlayerMoveEvent $event): void {
        if (!$this->recorder->canRecordEntity($player = $event->getPlayer())) {
            return;
        }
        $this->recorder->recordEntityMovement($player, $event->getTo());
    }

    /** @priority MONITOR */
    public function onSkinChange(PlayerChangeSkinEvent $event): void {
        if (!$this->recorder->canRecordEntity($player = $event->getPlayer())) {
            return;
        }
        $this->recorder->addAction(EntityChangeSkinAction::create($player->getId(), $event->getNewSkin()));
    }

    /** @priority MONITOR */
    public function onItemHeld(PlayerItemHeldEvent $event): void {
        if ($event->isCancelled() || !$this->recorder->canRecordEntity($player = $event->getPlayer()) || !$player->isAlive()) {
            return;
        }
        $this->recorder->addAction(EntityEquipAction::create($player->getId(), $event->getItem()));
        $this->recorder->addAction(EntityInventoryAction::create($player, heldSlot: $event->getSlot()));
    }

    /** @priority MONITOR */
    public function onPlayerRespawn(PlayerRespawnEvent $event): void {
        if (!$this->recorder->canRecordEntity($player = $event->getPlayer())) {
            return;
        }
        $this->recorder->addEntity($player);
    }

    /** @priority MONITOR */
    public function onItemSpawn(ItemSpawnEvent $event): void {
        if (!$this->recorder->canRecordEntity($entity = $event->getEntity())) {
            return;
        }
        $this->recorder->addEntity($entity);
    }

    /** @priority MONITOR */
    public function onEntitySpawn(EntitySpawnEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof ItemEntity || !$this->recorder->canRecordEntity($entity)) {
            return;
        }
        $this->recorder->addEntity($entity);
    }

    /** @priority MONITOR */
    public function onEntityTeleport(EntityTeleportEvent $event): void {
        $from = $event->getFrom();
        $to   = $event->getTo();
        $entity = $event->getEntity();

        if ($entity->getWorld() !== $this->recorder->getWorld() && $to->getWorld() === $this->recorder->getWorld()) {
            $this->recorder->addEntity($entity);
            return;
        }

        if (!$this->recorder->canRecordEntity($entity) || !$entity->isAlive()) {
            return;
        }

        if ($from->getWorld() !== $to->getWorld()) {
            $this->recorder->removeEntity($entity);
            return;
        }

        $location = $entity->getLocation();
        $this->recorder->recordEntityMovement(
            $entity,
            Location::fromObject($to, $to->getWorld(), $location->getYaw(), $location->getPitch()),
            true
        );
    }

    /** @priority MONITOR */
    public function onEntityDamage(EntityDamageEvent $event): void {
        if (!$this->recorder->canRecordEntity($entity = $event->getEntity()) || !$entity->isAlive()) {
            return;
        }
        $this->recorder->addAction(ActorEventAction::create($entity->getId(), ActorEvent::HURT_ANIMATION, 0, null));
    }

    /** @priority MONITOR */
    public function onEffectAdd(EntityEffectAddEvent $event): void {
        $entity = $event->getEntity();
        if ($event->isCancelled() || !$entity instanceof Living || !$this->recorder->canRecordEntity($entity)) {
            return;
        }
        $action = EntityEffectAction::create($entity->getId(), $event->getEffect());
        if ($action !== null) {
            $this->recorder->addAction($action);
        }
    }

    /** @priority MONITOR */
    public function onEffectRemove(EntityEffectRemoveEvent $event): void {
        $entity = $event->getEntity();
        if ($event->isCancelled() || !$entity instanceof Living || !$this->recorder->canRecordEntity($entity)) {
            return;
        }
        $action = EntityEffectAction::createRemoved($entity->getId(), $event->getEffect()->getType());
        if ($action !== null) {
            $this->recorder->addAction($action);
        }
    }

    /** @priority MONITOR */
    public function onEntityDeath(EntityDeathEvent $event): void {
        if (!$this->recorder->canRecordEntity($entity = $event->getEntity())) {
            return;
        }
        $this->recorder->addAction(ActorDeathAction::create($entity->getId()));
        if ($entity instanceof Player) {
            $killer = $entity->getLastDamageCause()?->getEntity();
            if (!$killer instanceof Player || $killer === $entity) {
                $killer = null;
            }
            $this->recorder->addEventLog(PlayerDeathEventLog::create($entity, $killer));
        }
    }

    /** @priority MONITOR */
    public function onDespawn(EntityDespawnEvent $event): void {
        if (!$this->recorder->canRecordEntity($entity = $event->getEntity())) {
            return;
        }
        $this->recorder->removeEntity($entity);
    }

    /** @priority MONITOR */
    public function onPacketReceive(DataPacketReceiveEvent $event): void {
        $player = $event->getOrigin()->getPlayer();

        if ($player === null || $player->getWorld() !== $this->recorder->getWorld()) {
            return;
        }

        $event->getPacket()->handle($this->packetHandler);
    }

    /** @priority MONITOR */
    public function onPacketSend(DataPacketSendEvent $event): void {
        foreach ($event->getTargets() as $target) {
            $player = $target->getPlayer();
            if ($player === null || $player->getWorld() !== $this->recorder->getWorld()) {
                continue;
            }
            foreach ($event->getPackets() as $packet) {
                $packet->handle($this->packetHandler);
            }
            break; // only need one target for outgoing broadcasts
        }
    }

    /** @priority MONITOR */
    public function onSignChange(SignChangeEvent $event): void {
        if ($event->getBlock()->getPosition()->getWorld() !== $this->recorder->getWorld()) {
            return;
        }
        $block = $event->getBlock();
        if ($block instanceof BaseSign) {
            $this->recorder->addAction(SignChangeAction::create($block));
        }
    }

    /** @priority MONITOR */
    public function onExplode(EntityExplodeEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
        if ($event->getPosition()->getWorld() !== $this->recorder->getWorld()) {
            return;
        }
        foreach ($event->getBlockList() as $block) {
            $pos = $block->getPosition();
            $air = VanillaBlocks::AIR();
            [$x, $y, $z] = [$pos->getFloorX(),$pos->getFloorY(),$pos->getFloorZ()];
            $air->position($pos->getWorld(), $x, $y, $z);
            $this->recorder->addAction(SetBlockAction::createAt($air, $pos->getWorld(), $x, $y, $z));
        }
    }

    /** @priority MONITOR */
    public function onBlockExplode(BlockExplodeEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
        foreach ($event->getAffectedBlocks() as $block) {
            $this->recordBlockState($block, VanillaBlocks::AIR());
        }
        foreach ($event->getIgnitions() as $block) {
            $this->recordBlockState($block, VanillaBlocks::FIRE());
        }
    }

    /** @priority MONITOR */
    public function onBlockBreak(BlockBreakEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
        $this->recordBlockState($event->getBlock(), VanillaBlocks::AIR());
    }

    /** @priority MONITOR */
    public function onBlockPlace(BlockPlaceEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
        $world = $event->getBlockAgainst()->getPosition()->getWorld();
        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            $this->recorder->addAction(SetBlockAction::createAt($block, $world, $x, $y, $z));
        }
    }

    /** @priority MONITOR */
    public function onBlockBurn(BlockBurnEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
        $this->recordBlockState($event->getBlock(), VanillaBlocks::AIR());
    }

    /** @priority MONITOR */
    public function onBlockGrow(BlockGrowEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
        $this->recordBlockState($event->getBlock(), $event->getNewState());
    }

    /** @priority MONITOR */
    public function onBlockSpread(BlockSpreadEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
        $this->recordBlockState($event->getBlock(), $event->getNewState());
    }

    /** @priority MONITOR */
    public function onBlockForm(BlockFormEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
        $this->recordBlockState($event->getBlock(), $event->getNewState());
    }

    /** @priority MONITOR */
    public function onBlockMelt(BlockMeltEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
        $this->recordBlockState($event->getBlock(), $event->getNewState());
    }

    /** @priority MONITOR */
    public function onBlockDeath(BlockDeathEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
        $this->recordBlockState($event->getBlock(), $event->getNewState());
    }

    /** @priority MONITOR */
    public function onLeavesDecay(LeavesDecayEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
        $this->recordBlockState($event->getBlock(), VanillaBlocks::AIR());
    }

    private function recordBlockState(Block $oldBlock, Block $newBlock): void {
        $position = $oldBlock->getPosition();
        if ($position->getWorld() !== $this->recorder->getWorld()) {
            return;
        }
        $x = $position->getFloorX();
        $y = $position->getFloorY();
        $z = $position->getFloorZ();
        $newBlock = clone $newBlock;
        $newBlock->position($position->getWorld(), $x, $y, $z);
        $this->recorder->addAction(SetBlockAction::createAt($newBlock, $position->getWorld(), $x, $y, $z));
    }

    /** @priority MONITOR */
    public function onChunkLoad(ChunkLoadEvent $event): void {
        if ($event->getWorld() !== $this->recorder->getWorld()) {
            return;
        }
        $this->recorder->addChunk(
            $event->getWorld(),
            $event->getChunk(),
            World::chunkHash($event->getChunkX(), $event->getChunkZ())
        );
    }

    public function onChunkUnload(ChunkUnloadEvent $event): void {
        if ($event->getWorld() !== $this->recorder->getWorld()) {
            return;
        }

        $this->recorder->removeChunk($event->getWorld(), $event->getChunk(), World::chunkHash($event->getChunkX(), $event->getChunkZ()));
    }

    public function onWorldUnload(WorldUnloadEvent $event): void {
        if($event->getWorld() === $this->recorder->getWorld()) {
            throw IlegalHandlingException::create("Trying to unload a world that is being recorded (world=". $this->recorder->getWorld()->getFolderName().")");
        }
    }
}
