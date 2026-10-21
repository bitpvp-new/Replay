<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type;

use bitpvp\replay\action\Action;
use bitpvp\replay\utils\BinaryStream;

abstract class EntityAction extends Action {

    protected int $entityId;

    public function getEntityId(): int {
        return $this->entityId;
    }

    public function serialize(BinaryStream $stream): void {
        $stream->putVarInt($this->entityId);
    }

    public function deserialize(BinaryStream $stream): void {
        $this->entityId = $stream->getVarInt();
    }
}
