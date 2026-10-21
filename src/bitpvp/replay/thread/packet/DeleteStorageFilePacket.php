<?php

declare(strict_types=1);

namespace bitpvp\replay\thread\packet;

use bitpvp\replay\thread\result\DeleteStorageFileResultPacket;
use bitpvp\replay\thread\ThreadPacket;

final class DeleteStorageFilePacket extends ThreadPacket {

    public function __construct(
        private readonly string $token,
        private readonly string $jsonPaths,
    ) {}

    public function execute(): ?ThreadPacket {
        $paths = json_decode($this->jsonPaths, true);
        if (is_array($paths)) {
            foreach ($paths as $path) {
                if (is_string($path) && file_exists($path)) {
                    @unlink($path);
                }
            }
        }
        return new DeleteStorageFileResultPacket($this->token, true, null);
    }
}
