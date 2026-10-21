<?php

declare(strict_types=1);

namespace bitpvp\replay\entity\type;

use bitpvp\replay\entity\ReplayEntity;
use bitpvp\replay\entity\ReplayEntityTrait;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;

class ReplayHuman extends Human implements ReplayEntity {
    use ReplayEntityTrait;

    public function __construct(
        protected int $actualId,
        private bool $isPlayer,
        private EntitySizeInfo $sizeInfo,
        private Vector3 $offsetPosition,
        Location $location,
        Skin $skin,
        ?CompoundTag $nbt = null
    ) {
        parent::__construct($location, $skin, $nbt);
        $this->setCanSaveWithChunk(false);
        $manager = $this->effectManager = new ReplayEffectManager($this);
        $manager->getEffectAddHooks()->add(function(EffectInstance $effect, bool $replacesOldEffect): void {
            $this->networkPropertiesDirty = true;
        });
        $manager->getEffectRemoveHooks()->add(function(EffectInstance $effect): void {
            $this->networkPropertiesDirty = true;
        });
    }

    public function isPlayer(): bool {
        return $this->isPlayer;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return $this->sizeInfo;
    }

    public function getOffsetPosition(Vector3 $vector3): Vector3 {
        return $this->offsetPosition->addVector($vector3);
    }

    public function getCustomNetworkTypeId(): string {
        return self::getNetworkTypeId();
    }
}
