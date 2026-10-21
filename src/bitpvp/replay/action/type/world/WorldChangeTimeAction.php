<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\world;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\WorldAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;

class WorldChangeTimeAction extends WorldAction {

    protected const int ID = ActionIds::WORLD_CHANGE_TIME;

    private int $time;

    public static function create(int $time): self {
        $action = new self();
        $action->time = $time;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        $stream->putInt($this->time);
    }

    public function deserialize(BinaryStream $stream): void {
        $this->time = $stream->getInt();
    }

    public function handle(ReplayPlayer $replay): void {
        $replay->getWorld()?->setTime($this->time);
    }

    public function handleReversed(ReplayPlayer $replay): ?Action {
        return self::create($replay->getWorld()?->getTime() ?? $this->time);
    }
}
