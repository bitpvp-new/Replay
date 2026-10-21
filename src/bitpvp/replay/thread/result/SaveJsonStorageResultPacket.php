<?php

declare(strict_types=1);

namespace bitpvp\replay\thread\result;

use bitpvp\replay\thread\ThreadPacket;

final class SaveJsonStorageResultPacket extends ThreadPacket {

    public function __construct(
        private readonly string $replayId,
        private readonly bool $success,
        private readonly string $message,
    ) {}

    public function execute(): ?ThreadPacket {
        return null;
    }

    public function getReplayId(): string {
        return $this->replayId;
    }

    public function isSuccess(): bool {
        return $this->success;
    }

    public function getMessage(): string {
        return $this->message;
    }
}
