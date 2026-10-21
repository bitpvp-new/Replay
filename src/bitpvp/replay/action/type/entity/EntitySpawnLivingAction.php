<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\entity;

use bitpvp\replay\action\ActionIds;

final class EntitySpawnLivingAction extends EntitySpawnAction {

    protected const int ID = ActionIds::ENTITY_SPAWN_LIVING;

    protected function shouldCreateLiving(): bool {
        return true;
    }
}
