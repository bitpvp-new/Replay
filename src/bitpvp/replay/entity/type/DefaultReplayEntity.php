<?php

declare(strict_types=1);

namespace bitpvp\replay\entity\type;

use bitpvp\replay\entity\CustomNetworkIdTrait;
use bitpvp\replay\entity\ReplayEntity;
use bitpvp\replay\entity\ReplayEntityTrait;
use pocketmine\entity\Entity;

class DefaultReplayEntity extends Entity implements ReplayEntity {
    use ReplayEntityTrait;
    use CustomNetworkIdTrait;
}
