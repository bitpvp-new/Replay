<?php

declare(strict_types=1);

namespace bitpvp\replay\thread\packet;

use bitpvp\replay\thread\result\CompressResultPacket;
use bitpvp\replay\thread\ThreadPacket;

final class CompressPacket extends ThreadPacket {

    public function __construct(
        private readonly string $token,
        private readonly string $rawBuffer,
    ) {}

    public function execute(): ?ThreadPacket {
        $compressed = zlib_encode($this->rawBuffer, ZLIB_ENCODING_GZIP, 6);
        if ($compressed === false) {
            return new CompressResultPacket($this->token, false, "", "zlib_encode failed");
        }
        return new CompressResultPacket($this->token, true, $compressed, null);
    }
}
