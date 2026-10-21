<?php

declare(strict_types=1);

namespace bitpvp\replay\recorder;

use bitpvp\replay\storage\ReplayStorage;
use pocketmine\plugin\Plugin;

final class RecordSettings {

    public function __construct(
        private readonly Plugin $plugin,
        private readonly string $savePath = "",
        private readonly bool $recordWorldSnapshot = true,
        private readonly int $recordInterval = 1,
        private readonly ?ReplayStorage $storage = null,
        private readonly int $serializationBatchSize = 500,
    ) {
        if ($recordInterval < 1) {
            throw new \InvalidArgumentException("Record interval must be at least one tick");
        }
        if ($serializationBatchSize < 1) {
            throw new \InvalidArgumentException("Serialization batch size must be at least one item per tick");
        }
    }

    public function getPlugin(): Plugin {
        return $this->plugin;
    }

    public function getSavePath(): string {
        return rtrim($this->savePath, '/\\');
    }

    public function shouldRecordWorldSnapshot(): bool {
        return $this->recordWorldSnapshot;
    }

    public function getRecordInterval(): int {
        return $this->recordInterval;
    }

    public function getStorage(): ?ReplayStorage {
        return $this->storage;
    }

    public function getSerializationBatchSize(): int {
        return $this->serializationBatchSize;
    }
}
