<?php

declare(strict_types=1);

namespace bitpvp\replay\thread\packet;

use bitpvp\replay\thread\result\SaveJsonStorageResultPacket;
use bitpvp\replay\thread\ThreadPacket;

final class SaveJsonStoragePacket extends ThreadPacket {

    public function __construct(
        private readonly string $replayId,
        private readonly string $jsonPath,
        private readonly string $jsonData,
        private readonly string $replayPath,
        private readonly string $buffer,
        private readonly bool $isCompressed = false,
    ) {}

    public function execute(): ?ThreadPacket {
        $dir = dirname($this->replayPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $compressed = $this->isCompressed ? $this->buffer : zlib_encode($this->buffer, ZLIB_ENCODING_GZIP, 6);
        if ($compressed === false) {
            return new SaveJsonStorageResultPacket($this->replayId, false, "zlib compression failed");
        }

        if (file_put_contents($this->replayPath, $compressed) === false) {
            return new SaveJsonStorageResultPacket($this->replayId, false, "Failed to write replay data file");
        }

        if (file_put_contents($this->jsonPath, $this->jsonData) === false) {
            @unlink($this->replayPath);
            return new SaveJsonStorageResultPacket($this->replayId, false, "Failed to write replay json metadata");
        }

        return new SaveJsonStorageResultPacket($this->replayId, true, $this->replayPath);
    }
}
