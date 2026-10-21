<?php

declare(strict_types=1);

namespace bitpvp\replay\entity\type;

use bitpvp\replay\entity\CustomNetworkIdTrait;
use bitpvp\replay\entity\ReplayEntity;
use bitpvp\replay\entity\ReplayEntityTrait;
use pocketmine\entity\Living;
use pocketmine\player\Player;

class ReplayLiving extends Living implements ReplayEntity {
    use ReplayEntityTrait;
    use CustomNetworkIdTrait;

    public function getName(): string {
        return "";
    }

    protected function sendSpawnPacket(Player $player): void {
        $this->sendInternalSpawnPacket($player);
        $networkSession = $player->getNetworkSession();
        $networkSession->getEntityEventBroadcaster()->onMobArmorChange([$networkSession], $this);
    }
}
