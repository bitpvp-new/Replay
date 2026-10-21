<?php

declare(strict_types=1);

namespace bitpvp\replay\entity\type;

use pocketmine\entity\effect\EffectManager;

final class ReplayEffectManager extends EffectManager {

    public function tick(int $tickDiff = 1): bool {
        // Replay actions control effect lifetimes; do not apply gameplay effects to the visual entity.
        return false;
    }
}
