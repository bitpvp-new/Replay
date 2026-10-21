<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\actor;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\entity\EntitySpawnAction;
use bitpvp\replay\action\type\entity\EntitySpawnItemAction;
use bitpvp\replay\action\type\EntityAction;
use bitpvp\replay\entity\type\ReplayItemEntity;
use bitpvp\replay\replayer\ReplayPlayer;

class ActorDespawnAction extends EntityAction {

    protected const int ID = ActionIds::ACTOR_DESPAWN;

    public static function create(int $entityId): self {
        $action = new self();
        $action->entityId = $entityId;
        return $action;
    }

    public function handle(ReplayPlayer $replay): void {
        $replay->despawnEntity($this->entityId);
    }

    public function handleReversed(ReplayPlayer $replay): ?Action {
        $entity = $replay->getEntity($this->entityId);
        if ($entity === null || !$entity->isAlive()) {
            return null;
        }
        return $entity instanceof ReplayItemEntity ? EntitySpawnItemAction::create($entity) : EntitySpawnAction::create($entity);
    }
}
