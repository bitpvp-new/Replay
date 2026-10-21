<?php

declare(strict_types=1);

namespace bitpvp\replay\replayer;

use pocketmine\plugin\Plugin;

final class ReplaySettings {

    public function __construct(
        private readonly Plugin $plugin,
        private readonly float $speed = 1.0,
        private readonly bool $loop = false,
        private readonly int $serializationBatchSize = 500,
    ) {
        if (!is_finite($speed) || $speed <= 0.0) {
            throw new \InvalidArgumentException("Replay speed must be a finite number greater than zero");
        }
        if ($serializationBatchSize < 1) {
            throw new \InvalidArgumentException("Serialization batch size must be at least one item per tick");
        }
    }

    public function getPlugin(): Plugin {
        return $this->plugin;
    }

    public function getSpeed(): float {
        return $this->speed;
    }

    public function isLoop(): bool {
        return $this->loop;
    }

    public function getSerializationBatchSize(): int {
        return $this->serializationBatchSize;
    }
}
