<?php

declare(strict_types=1);

namespace bitpvp\replay\log;

use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;

abstract class EventLog {

    protected const int ID = EventLogIds::NONE;

    public static function id(): int {
        return static::ID;
    }

    public static function getTickOffset(): int {
        return 0;
    }

    abstract public static function getName(): string;

    abstract public function getDisplayData(): string;

    abstract public function serialize(BinaryStream $stream): void;

    abstract public function deserialize(BinaryStream $stream): void;

    abstract public function handle(ReplayPlayer $replay): void;
}
