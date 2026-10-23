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
    private float $animationData;
    private ?string $swingSource = null;

    public static function create(int $entityId, int $animationType, float $animationData, ?string $swingSource): self {
        $action = new self();
        $action->entityId = $entityId;
        $action->animationType = $animationType;
        $action->animationData = $animationData;
        $action->swingSource = $swingSource;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        parent::serialize($stream);
        $stream->putInt($this->animationType);
        $stream->putFloat($this->animationData);
        $stream->putBool($this->swingSource !== null);
        if($this->swingSource !== null) $stream->putString($this->swingSource);
    }

    public function deserialize(BinaryStream $stream): void {
        parent::deserialize($stream);
        $this->animationType = $stream->getInt();
        $this->animationData = $stream->getFloat();
        if($stream->getBool()){
            $this->swingSource = $stream->getString();
        }
    }

    public function handle(ReplayPlayer $replay): void {
        $entity = $replay->getEntity($this->entityId);
        if ($entity === null) {
            return;
        }
        $replay->getWorld()->broadcastPacketToViewers(
            $entity->getPosition(),
            AnimatePacket::create($entity->getId(), $this->animationType, $this->animationData, $this->swingSource)
        );
    }
}
