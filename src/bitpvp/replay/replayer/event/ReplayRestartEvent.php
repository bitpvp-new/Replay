<?php

declare(strict_types=1);

namespace bitpvp\replay\replayer\event;

use bitpvp\replay\replayer\ReplayPlayer;

class ReplayRestartEvent extends ReplayEvent {

    public function __construct(
        ReplayPlayer $replay,
        private bool $fromBeginning = true
    ) {
        parent::__construct($replay);
    }

    public function isFromBeginning(): bool {
        return $this->fromBeginning;
    }
}
