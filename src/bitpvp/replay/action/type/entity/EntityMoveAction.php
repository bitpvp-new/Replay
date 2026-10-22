<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\entity;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\EntityAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use pocketmine\entity\Location;
use pocketmine\world\Position;

class EntityMoveAction extends EntityAction {

    protected const int ID = ActionIds::ENTITY_MOVE;

    private Location $location;
    private bool $teleport;

    public static function create(int $entityId, Location $location, bool $teleport): self {
        $action = new self();
        $action->entityId = $entityId;
        $action->location = $location;
        $action->teleport = $teleport;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        parent::serialize($stream);
        $stream->putLocation($this->location);
        $stream->putBool($this->teleport);
    }

    public function deserialize(BinaryStream $stream): void {
        parent::deserialize($stream);
        $this->location = $stream->getLocation();
        $this->teleport = $stream->getBool();
    }

    public function handle(ReplayPlayer $replay): void {
        $entity = $replay->getEntity($this->entityId);
        if ($entity === null) {
            return;
        }
        $targetLoc = Location::fromObject($this->location, $replay->getWorld(), $this->location->getYaw(), $this->location->getPitch());
        if ($this->teleport) {
            $entity->teleport($targetLoc);
            return;
        }
        $entity->setPositionAndRotation($targetLoc, $targetLoc->yaw, $targetLoc->pitch);
    }

    public function handleReversed(ReplayPlayer $replay): ?Action {
        $entity = $replay->getEntity($this->entityId);
        if ($entity === null) {
            return null;
        }
        return self::create($this->entityId, $entity->getLocation(), $this->teleport);
    }
}
