<?php

declare(strict_types=1);

namespace bitpvp\replay\replayer\event;

use bitpvp\replay\replayer\ReplayPlayer;
use pocketmine\event\Event;

abstract class ReplayEvent extends Event {

    public function __construct(
        protected ReplayPlayer $replay
    ) {}

    public function getReplay(): ReplayPlayer {
        return $this->replay;
    }
}
