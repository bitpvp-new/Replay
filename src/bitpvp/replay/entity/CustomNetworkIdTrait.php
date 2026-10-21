<?php

declare(strict_types=1);

namespace bitpvp\replay\entity;

use pocketmine\entity\Attribute;
use pocketmine\entity\Living;
use pocketmine\entity\effect\EffectInstance;
use bitpvp\replay\entity\type\ReplayEffectManager;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;
use pocketmine\player\Player;

trait CustomNetworkIdTrait {

    public function __construct(
        private string $networkTypeId,
        private int $actualId,
        private EntitySizeInfo $sizeInfo,
        private Vector3 $offsetPosition,
        Location $location,
        ?CompoundTag $nbt = null
    ) {
        parent::__construct($location, $nbt);
        $this->setCanSaveWithChunk(false);
        if ($this instanceof Living) {
            $manager = $this->effectManager = new ReplayEffectManager($this);
            $manager->getEffectAddHooks()->add(function(EffectInstance $effect, bool $replacesOldEffect): void {
                $this->networkPropertiesDirty = true;
            });
            $manager->getEffectRemoveHooks()->add(function(EffectInstance $effect): void {
                $this->networkPropertiesDirty = true;
            });
        }
    }

    public static function getNetworkTypeId(): string {
        return EntityIds::XP_ORB;
    }

    protected function getInitialGravity(): float {
        return 0.08;
    }

    protected function getInitialDragMultiplier(): float {
        return 0.02;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return $this->sizeInfo;
    }

    public function getCustomNetworkTypeId(): string {
        return $this->networkTypeId;
    }

    public function getOffsetPosition(Vector3 $vector3): Vector3 {
        return $this->offsetPosition->addVector($vector3);
    }

    protected function sendSpawnPacket(Player $player): void {
        $this->sendInternalSpawnPacket($player);
    }

    private function sendInternalSpawnPacket(Player $player): void {
        $player->getNetworkSession()->sendDataPacket(AddActorPacket::create(
            $this->getId(),
            $this->getId(),
            $this->networkTypeId,
            $this->location->asVector3(),
            $this->getMotion(),
            $this->location->pitch,
            $this->location->yaw,
            $this->location->yaw,
            $this->location->yaw,
            array_map(fn(Attribute $attribute): NetworkAttribute => new NetworkAttribute(
                $attribute->getId(),
                $attribute->getMinValue(),
                $attribute->getMaxValue(),
                $attribute->getValue(),
                $attribute->getDefaultValue(),
                []
            ), $this->attributeMap->getAll()),
            $this->getAllNetworkData(),
            new PropertySyncData([], []),
            []
        ));
    }
}
