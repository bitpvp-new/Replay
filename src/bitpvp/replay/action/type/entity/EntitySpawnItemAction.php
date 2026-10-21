<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\entity;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\actor\ActorDespawnAction;
use bitpvp\replay\action\type\EntityAction;
use bitpvp\replay\entity\type\ReplayItemEntity;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\Item;
use pocketmine\math\Vector3;

class EntitySpawnItemAction extends EntityAction {

    protected const int ID = ActionIds::ITEM_SPAWN;

    private Item $item;
    private Vector3 $position;

    public static function create(ItemEntity $entity): static {
        $action = new static();
        $action->entityId = $entity instanceof ReplayItemEntity ? $entity->getActualId() : $entity->getId();
        $action->item = $entity->getItem();
        $action->position = $entity->getPosition();
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        parent::serialize($stream);
        $stream->putItem($this->item);
        $stream->putVector3($this->position);
    }

    public function deserialize(BinaryStream $stream): void {
        parent::deserialize($stream);
        $this->item = $stream->getItem();
        $this->position = $stream->getVector3();
    }

    public function handle(ReplayPlayer $replay): void {
        $replay->spawnEntity(new ReplayItemEntity($this->entityId, Location::fromObject($this->position, $replay->getWorld()), $this->item));
    }

    public function handleReversed(ReplayPlayer $replay): ?Action {
        return ActorDespawnAction::create($this->entityId);
    }
}
