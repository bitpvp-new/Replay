<?php

declare(strict_types=1);

namespace bitpvp\replay\thread\result;

use bitpvp\replay\thread\ThreadPacket;

final class FetchAllJsonStorageResultPacket extends ThreadPacket {

    public function __construct(
        private readonly string $token,
        private readonly bool $success,
        private readonly string $itemsJson,
        private readonly ?string $error,
    ) {}

    public function execute(): ?ThreadPacket {
        return null;
    }

    public function getToken(): string {
        return $this->token;
    }

    public function isSuccess(): bool {
        return $this->success;
    }

    public function getItemsJson(): string {
        return $this->itemsJson;
    }

    public function getError(): ?string {
        return $this->error;
    }
}
