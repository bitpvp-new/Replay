<?php

declare(strict_types=1);

namespace bitpvp\replay\thread\packet;

use bitpvp\replay\thread\result\LoadJsonStorageResultPacket;
use bitpvp\replay\thread\ThreadPacket;

final class LoadJsonStoragePacket extends ThreadPacket {

    public function __construct(
        private readonly string $replayId,
        private readonly string $jsonPath,
        private readonly string $replayPath,
    ) {}

    public function execute(): ?ThreadPacket {
        if (!file_exists($this->jsonPath)) {
            return new LoadJsonStorageResultPacket($this->replayId, false, "", null, "Metadata file not found: {$this->jsonPath}");
        }
        if (!file_exists($this->replayPath)) {
            return new LoadJsonStorageResultPacket($this->replayId, false, "", null, "Replay file not found: {$this->replayPath}");
        }

        $jsonRaw = file_get_contents($this->jsonPath);
        if ($jsonRaw === false) {
            return new LoadJsonStorageResultPacket($this->replayId, false, "", null, "Failed to read metadata file: {$this->jsonPath}");
        }

        $compressed = file_get_contents($this->replayPath);
        if ($compressed === false) {
            return new LoadJsonStorageResultPacket($this->replayId, false, "", null, "Failed to read replay file: {$this->replayPath}");
        }

        $rawBuffer = zlib_decode($compressed);
        if ($rawBuffer === false) {
            return new LoadJsonStorageResultPacket($this->replayId, false, "", null, "zlib_decode failed: corrupted file");
        }

        return new LoadJsonStorageResultPacket($this->replayId, true, $jsonRaw, $rawBuffer, null);
    }
}
