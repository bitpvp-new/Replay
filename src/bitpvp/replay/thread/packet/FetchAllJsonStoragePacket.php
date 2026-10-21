<?php

declare(strict_types=1);

namespace bitpvp\replay\thread\packet;

use bitpvp\replay\thread\result\FetchAllJsonStorageResultPacket;
use bitpvp\replay\thread\ThreadPacket;

final class FetchAllJsonStoragePacket extends ThreadPacket {

    public function __construct(
        private readonly string $token,
        private readonly string $directory,
    ) {}

    public function execute(): ?ThreadPacket {
        if (!is_dir($this->directory)) {
            return new FetchAllJsonStorageResultPacket($this->token, true, "[]", null);
        }

        $files = glob(rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . "*.json");
        if ($files === false) {
            return new FetchAllJsonStorageResultPacket($this->token, false, "[]", "Failed to scan directory");
        }

        $items = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $items[] = $decoded;
                }
            }
        }

        $encoded = json_encode($items);
        return new FetchAllJsonStorageResultPacket($this->token, true, $encoded !== false ? $encoded : "[]", null);
    }
}
