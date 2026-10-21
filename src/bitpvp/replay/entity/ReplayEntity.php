<?php

declare(strict_types=1);

namespace bitpvp\replay\entity;

use pocketmine\entity\NeverSavedWithChunkEntity;

interface ReplayEntity extends NeverSavedWithChunkEntity {

    public function getActualId(): int;

    public function getCustomNetworkTypeId(): string;
}
