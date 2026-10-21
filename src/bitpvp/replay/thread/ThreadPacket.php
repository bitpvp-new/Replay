<?php

declare(strict_types=1);

namespace bitpvp\replay\thread;

use pmmp\thread\ThreadSafe;

abstract class ThreadPacket extends ThreadSafe {

    abstract public function execute(): ?ThreadPacket;
}
