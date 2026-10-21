<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\actor;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\entity\EntitySpawnAction;
use bitpvp\replay\action\type\EntityAction;
use bitpvp\replay\replayer\ReplayPlayer;

class ActorDeathAction extends EntityAction {

    protected const int ID = ActionIds::ACTOR_DEATH;

    public static function create(int $entityId): self {
        $action = new self();
        $action->entityId = $entityId;
        return $action;
    }

    public function handle(ReplayPlayer $replay): void {
        $replay->getEntity($this->entityId)?->kill();
    }

    public function handleReversed(ReplayPlayer $replay): ?Action {
        $entity = $replay->getEntity($this->entityId);
        return $entity !== null ? EntitySpawnAction::create($entity) : null;
    }
}
