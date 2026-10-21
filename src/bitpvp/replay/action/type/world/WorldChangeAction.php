<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\world;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\WorldAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;

class WorldChangeAction extends WorldAction {

    protected const int ID = ActionIds::WORLD_CHANGE;

    private string $worldName;

    public static function create(string $worldName): self {
        $action = new self();
        $action->worldName = $worldName;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        $stream->putString($this->worldName);
    }

    public function deserialize(BinaryStream $stream): void {
        $this->worldName = $stream->getString();
    }

    public function handle(ReplayPlayer $replay): void {
        $replay->switchWorld($this->worldName);
    }

    public function handleReversed(ReplayPlayer $replay): ?Action {
        return self::create($replay->getWorld()?->getDisplayName() ?? $this->worldName);
    }
}
