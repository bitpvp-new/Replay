<?php

declare(strict_types=1);

namespace bitpvp\replay\thread\packet;

use bitpvp\replay\thread\result\SaveReplayResultPacket;
use bitpvp\replay\thread\ThreadPacket;

final class SaveReplayPacket extends ThreadPacket {

    public function __construct(
        private readonly string $replayId,
        private readonly string $outputPath,
        private readonly string $rawBuffer,
    ) {}

    public function execute(): ?ThreadPacket {
        $compressed = zlib_encode($this->rawBuffer, ZLIB_ENCODING_GZIP, 6);
        if ($compressed === false) {
            return new SaveReplayResultPacket($this->replayId, false, "zlib_encode failed");
        }

        $dir = dirname($this->outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (file_put_contents($this->outputPath, $compressed) === false) {
            return new SaveReplayResultPacket($this->replayId, false, "Failed to write file: {$this->outputPath}");
        }

        return new SaveReplayResultPacket($this->replayId, true, $this->outputPath);
    }
}
