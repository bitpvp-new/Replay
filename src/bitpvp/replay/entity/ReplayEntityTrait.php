<?php

declare(strict_types=1);

namespace bitpvp\replay\entity;

use pocketmine\math\Vector3;

trait ReplayEntityTrait {

    public function getActualId(): int {
        return $this->actualId;
    }

    public function canBeMovedByCurrents(): bool {
        return false;
    }

    public function hasMovementUpdate(): bool {
        return false;
    }

    public function canBeCollidedWith(): bool {
        return false;
    }

    public function getDrops(): array {
        return [];
    }

    public function setPositionAndRotation(Vector3 $pos, float $yaw, float $pitch): bool {
        return parent::setPositionAndRotation($pos, $yaw, $pitch);
    }
}
