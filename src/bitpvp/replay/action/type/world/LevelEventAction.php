<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\world;

use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\WorldAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;

class LevelEventAction extends WorldAction {

    protected const int ID = ActionIds::LEVEL_EVENT;

    private int $eventId;
    private int $eventData;
    private ?Vector3 $position = null;

    public static function create(int $eventId, int $eventData, ?Vector3 $position): self {
        $action = new self();
        $action->eventId = $eventId;
        $action->eventData = $eventData;
        $action->position = $position;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        $stream->putVarInt($this->eventId);
        $stream->putVarInt($this->eventData);
        $stream->putVector3Nullable($this->position);
    }

    public function deserialize(BinaryStream $stream): void {
        $this->eventId = $stream->getVarInt();
        $this->eventData = $stream->getVarInt();
        $this->position = $stream->getVector3Nullable();
    }

    public function handle(ReplayPlayer $replay): void {
        $pos = $this->position ?? $replay->getWorld()->getSpawnLocation();
        $replay->getWorld()->broadcastPacketToViewers(
            $pos,
            LevelEventPacket::create($this->eventId, $this->eventData, $this->position)
        );
    }
}
