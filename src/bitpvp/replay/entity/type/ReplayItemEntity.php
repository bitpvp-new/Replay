<?php

declare(strict_types=1);

namespace bitpvp\replay\entity\type;

use bitpvp\replay\entity\ReplayEntity;
use bitpvp\replay\entity\ReplayEntityTrait;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;

class ReplayItemEntity extends ItemEntity implements ReplayEntity {
    use ReplayEntityTrait;

    public function __construct(
        protected int $actualId,
        Location $location,
        Item $item,
        ?CompoundTag $nbt = null
    ) {
        parent::__construct($location, $item, $nbt);
        $this->setCanSaveWithChunk(false);
    }

    public function getCustomNetworkTypeId(): string {
        return self::getNetworkTypeId();
    }
}
