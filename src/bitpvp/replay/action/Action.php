<?php

declare(strict_types=1);

namespace bitpvp\replay\action;

use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;

abstract class Action {

    protected const int ID = ActionIds::NONE;

    public static function id(): int {
        return static::ID;
    }

    abstract public function serialize(BinaryStream $stream): void;

    abstract public function deserialize(BinaryStream $stream): void;

    abstract public function handle(ReplayPlayer $replay): void;

    public function handleReversed(ReplayPlayer $replay): ?Action {
        return $this;
    }
}
