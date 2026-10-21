<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\player;

use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\EntityAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use pocketmine\network\mcpe\protocol\AnimatePacket;

class PlayerAnimationAction extends EntityAction {

    protected const int ID = ActionIds::PLAYER_ANIMATION;

    private int $animationType;

    public static function create(int $entityId, int $animationType): self {
        $action = new self();
        $action->entityId = $entityId;
        $action->animationType = $animationType;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        parent::serialize($stream);
        $stream->putInt($this->animationType);
    }

    public function deserialize(BinaryStream $stream): void {
        parent::deserialize($stream);
        $this->animationType = $stream->getInt();
    }

    public function handle(ReplayPlayer $replay): void {
        $entity = $replay->getEntity($this->entityId);
        if ($entity === null) {
            return;
        }
        $replay->getWorld()->broadcastPacketToViewers(
            $entity->getPosition(),
            AnimatePacket::create($entity->getId(), $this->animationType)
        );
    }
}
