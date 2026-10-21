<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\player;

use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\EntityAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;

class PlayerChatAction extends EntityAction {

    protected const int ID = ActionIds::PLAYER_CHAT;
    private const string MESSAGE_PREFIX = "§8[§dReplay§8]§r ";

    private string $message;

    public static function create(int $entityId, string $message): self {
        $action = new self();
        $action->entityId = $entityId;
        $action->message = $message;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        parent::serialize($stream);
        $stream->putString($this->message);
    }

    public function deserialize(BinaryStream $stream): void {
        parent::deserialize($stream);
        $this->message = $stream->getString();
    }

    public function handle(ReplayPlayer $replay): void {
        $replay->getViewer()->sendMessage(self::MESSAGE_PREFIX . $this->message);
    }
}
