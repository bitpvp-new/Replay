<?php

namespace bitpvp\replay\listener\handler;

use bitpvp\replay\action\type\actor\ActorEventAction;
use bitpvp\replay\action\type\actor\ActorSetMetadataAction;
use bitpvp\replay\action\type\entity\EntityPlayEmoteAction;
use bitpvp\replay\action\type\player\PlayerAnimationAction;
use bitpvp\replay\action\type\world\BlockEventAction;
use bitpvp\replay\action\type\world\LevelEventAction;
use bitpvp\replay\action\type\world\SetBlockAction;
use bitpvp\replay\recorder\Recorder;
use pocketmine\network\mcpe\handler\PacketHandler;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\EmotePacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\world\World;

final class RecordPacketHandler extends PacketHandler {

    private World $world;

    public function __construct(
        private Recorder $recorder
    ){
        $this->world = $this->recorder->getWorld();
    }

    public function setUp(): void {

    }

    public function handleAnimate(AnimatePacket $packet): bool{
        $entity = $this->world->getEntity($packet->actorRuntimeId);
        if($entity === null || !$entity->isAlive() || !$this->recorder->canRecordEntity($entity)) {
            return false;
        }

        $this->recorder->addAction(PlayerAnimationAction::create($packet->actorRuntimeId, $packet->action, $packet->data, $packet->swingSource));
        return true;
    }

    public function handleActorEvent(ActorEventPacket $packet): bool{
        $entity = $this->world->getEntity($packet->actorRuntimeId);
        if($entity === null || !$entity->isAlive() || !$this->recorder->canRecordEntity($entity)) {
            return false;
        }
        $this->recorder->addAction(ActorEventAction::create($packet->actorRuntimeId, $packet->eventId, $packet->eventData, $packet->firePosition));
        return true;
    }

    public function handleEmote(EmotePacket $packet): bool{
        $entity = $this->world->getEntity($id = $packet->getActorRuntimeId());
        if($entity === null || !$entity->isAlive() || !$this->recorder->canRecordEntity($entity)) {
            return false;
        }
        $this->recorder->addAction(EntityPlayEmoteAction::create($id, $packet->getEmoteId()));
        return true;
    }

    public function handleLevelEvent(LevelEventPacket $packet): bool{
        $this->recorder->addAction(LevelEventAction::create($packet->eventId, $packet->eventData, $packet->position));
        return true;
    }

    public function handleSetActorData(SetActorDataPacket $packet): bool{
        $id = $packet->actorRuntimeId;
        $entity = $this->world->getEntity($id);
        if($entity === null || !$entity->isAlive() || !$this->recorder->canRecordEntity($entity)) {
            return false;
        }
        $this->recorder->addAction(ActorSetMetadataAction::create($id, $packet->metadata));
        return true;
    }

    public function handleBlockEvent(BlockEventPacket $packet): bool{
        $this->recorder->addAction(BlockEventAction::create($packet->eventType, $packet->eventData, $packet->blockPosition));
        return true;
    }

    public function handleUpdateBlock(UpdateBlockPacket $packet): bool{
        if($packet->dataLayerId !== UpdateBlockPacket::DATA_LAYER_NORMAL) return false;
        $position = $packet->blockPosition;
        $this->recorder->addAction(SetBlockAction::create($this->world->getBlockAt(
            $position->getX(),
            $position->getY(),
            $position->getZ()
        )));
        return true;
    }
}