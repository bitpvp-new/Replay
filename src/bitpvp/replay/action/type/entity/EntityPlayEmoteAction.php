<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\entity;

use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\EntityAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use pocketmine\entity\Human;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\NetworkBroadcastUtils;

class EntityPlayEmoteAction extends EntityAction {

    protected const int ID = ActionIds::ENTITY_PLAY_EMOTE;

    private string $emoteId;

    public static function create(int $entityId, string $emoteId): self {
        $action = new self();
        $action->entityId = $entityId;
        $action->emoteId = $emoteId;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        parent::serialize($stream);
        $stream->putString($this->emoteId);
    }

    public function deserialize(BinaryStream $stream): void {
        parent::deserialize($stream);
        $this->emoteId = $stream->getString();
    }

    public function handle(ReplayPlayer $replay): void {
        $entity = $replay->getEntity($this->entityId);
        if (!$entity instanceof Human) {
            return;
        }
        NetworkBroadcastUtils::broadcastEntityEvent(
            $entity->getViewers(),
            fn(EntityEventBroadcaster $broadcaster, array $recipients) => $broadcaster->onEmote($recipients, $entity, $this->emoteId)
        );
    }
}
