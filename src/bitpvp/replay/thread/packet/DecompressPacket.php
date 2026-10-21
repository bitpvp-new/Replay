<?php

declare(strict_types=1);

namespace bitpvp\replay\thread\packet;

use bitpvp\replay\thread\result\DecompressResultPacket;
use bitpvp\replay\thread\ThreadPacket;

final class DecompressPacket extends ThreadPacket {

    public function __construct(
        private readonly string $token,
        private readonly string $compressedBuffer,
    ) {}

    public function execute(): ?ThreadPacket {
        $raw = zlib_decode($this->compressedBuffer);
        if ($raw === false) {
            return new DecompressResultPacket($this->token, false, "", "zlib_decode failed");
        }
        return new DecompressResultPacket($this->token, true, $raw, null);
    }
}
