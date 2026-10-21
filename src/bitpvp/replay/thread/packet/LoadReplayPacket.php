<?php

declare(strict_types=1);

namespace bitpvp\replay\thread\packet;

use bitpvp\replay\thread\result\LoadReplayResultPacket;
use bitpvp\replay\thread\ThreadPacket;

final class LoadReplayPacket extends ThreadPacket {

    public function __construct(
        private readonly string $replayId,
        private readonly string $filePath,
    ) {}

    public function execute(): ?ThreadPacket {
        if (!file_exists($this->filePath)) {
            return new LoadReplayResultPacket($this->replayId, false, null, "File not found: {$this->filePath}");
        }

        $compressed = file_get_contents($this->filePath);
        if ($compressed === false) {
            return new LoadReplayResultPacket($this->replayId, false, null, "Failed to read file: {$this->filePath}");
        }

        $rawBuffer = zlib_decode($compressed);
        if ($rawBuffer === false) {
            return new LoadReplayResultPacket($this->replayId, false, null, "zlib_decode failed: corrupted file or invalid format");
        }

        return new LoadReplayResultPacket($this->replayId, true, $rawBuffer, null);
    }
}
