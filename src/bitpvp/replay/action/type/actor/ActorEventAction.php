<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\actor;

use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\EntityAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use pocketmine\network\mcpe\protocol\ActorEventPacket;

class ActorEventAction extends EntityAction {

    protected const int ID = ActionIds::ACTOR_EVENT;

    private int $eventId;
    private int $eventData;

    public static function create(int $entityId, int $eventId, int $eventData): self {
        $action = new self();
        $action->entityId = $entityId;
        $action->eventId = $eventId;
        $action->eventData = $eventData;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        parent::serialize($stream);
        $stream->putByte($this->eventId);
        $stream->putInt($this->eventData);
    }

    public function deserialize(BinaryStream $stream): void {
        parent::deserialize($stream);
        $this->eventId = $stream->getByte();
        $this->eventData = $stream->getInt();
    }

    public function handle(ReplayPlayer $replay): void {
        $entity = $replay->getEntity($this->entityId);
        if ($entity === null) {
            return;
        }
        $replay->getWorld()->broadcastPacketToViewers(
            $entity->getPosition(),
            ActorEventPacket::create($entity->getId(), $this->eventId, $this->eventData, null)
        );
    }
}
