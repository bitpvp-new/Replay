<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\world;

use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\WorldAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;

class BlockEventAction extends WorldAction {

    protected const int ID = ActionIds::BLOCK_EVENT;

    private int $eventType;
    private int $eventData;
    private BlockPosition $position;

    public static function create(int $eventType, int $eventData, BlockPosition $position): self {
        $action = new self();
        $action->eventType = $eventType;
        $action->eventData = $eventData;
        $action->position = $position;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        $stream->putVarInt($this->eventType);
        $stream->putVarInt($this->eventData);
        $stream->putBlockPosition($this->position);
    }

    public function deserialize(BinaryStream $stream): void {
        $this->eventType = $stream->getVarInt();
        $this->eventData = $stream->getVarInt();
        $this->position = $stream->getBlockPosition();
    }

    public function handle(ReplayPlayer $replay): void {
        $replay->getWorld()->broadcastPacketToViewers(
            new Vector3($this->position->getX(), $this->position->getY(), $this->position->getZ()),
            BlockEventPacket::create($this->position, $this->eventType, $this->eventData)
        );
    }
}
