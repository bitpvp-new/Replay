<?php

namespace bitpvp\replay\utils;

use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\Task;

final class ReplaySkipTickTask extends Task {

    private int $completedTicks = 0;
    public function __construct(
        protected \Closure $skip,
        protected int $skipTicks,
        private int $slice,
        private \Closure $completitionCallback
    ) {

    }

    public function onRun(): void {
        if($this->completedTicks >= $this->skipTicks) {
            throw new CancelTaskException();
        }

        $part = (int) ($this->skipTicks / $this->slice);

        for($i = 0; $i < $part; $i++) {
            if(!($this->skip)($i)){
                $this->completedTicks = $this->skipTicks;
                break;
            }
        }

        $this->completedTicks += $part;
    }

    public function onCancel(): void {
        ($this->completitionCallback)();
    }
}