<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\world;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\WorldAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use pocketmine\block\Block;
use pocketmine\block\tile\TileFactory;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;

class SetBlockAction extends WorldAction {

    protected const int ID = ActionIds::BLOCK_SET;

    private Block $block;
    private Vector3 $position;
    private ?CompoundTag $tile = null;

    public static function create(Block $block): self {
        $action = new self();
        $action->block = clone $block;
        $action->position = $pos = $block->getPosition();
        $action->tile = $pos->getWorld()->getTile($pos)?->saveNBT();
        return $action;
    }

    public static function createAt(Block $block, World $world, int $x, int $y, int $z, bool $captureTile = false): self {
        $action = new self();
        $action->block = clone $block;
        $action->position = new Vector3($x, $y, $z);
        $action->tile = $captureTile ? $world->getTileAt($x, $y, $z)?->saveNBT() : null;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        $stream->putBlock($this->block);
        $stream->putVector3($this->position);
        $stream->putBool($this->tile !== null);
        if ($this->tile !== null) {
            $stream->putNbtCompound($this->tile);
        }
    }

    public function deserialize(BinaryStream $stream): void {
        $this->block = $stream->getBlock();
        $this->position = $stream->getVector3();
        if ($stream->getBool()) {
            $this->tile = $stream->getNbtCompound();
        }
    }

    public function handle(ReplayPlayer $replay): void {
        $world = $replay->getWorld();
        $tile = $world->getTile($this->position);
        if ($tile !== null) {
            $world->removeTile($tile);
        }
        $world->setBlock($this->position, $this->block);
        if ($this->tile !== null) {
            $tile = TileFactory::getInstance()->createFromData($world, $this->tile);
            if ($tile !== null) {
                $world->addTile($tile);
            }
        }
    }

    public function handleReversed(ReplayPlayer $replay): ?Action {
        return self::create($replay->getWorld()->getBlock($this->position));
    }
}
