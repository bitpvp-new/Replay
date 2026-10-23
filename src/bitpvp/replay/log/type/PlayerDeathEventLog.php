<?php

declare(strict_types=1);

namespace bitpvp\replay\log\type;

use bitpvp\replay\log\EventLog;
use bitpvp\replay\log\EventLogIds;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use pocketmine\player\Player;

class PlayerDeathEventLog extends EventLog {

    protected const int ID = EventLogIds::DEATH_EVENT;

    private string $playerName;
    private int $playerId;
    private ?string $killerName = null;

    public static function create(Player $player, ?Player $killer): self {
        $data = new self();
        $data->playerName = $player->getDisplayName();
        $data->playerId = $player->getId();
        $data->killerName = $killer?->getDisplayName();
        return $data;
    }

    public static function getName(): string {
        return "deaths";
    }

    public static function getTickOffset(): int {
        return 4 * 20;
    }

    public function getDisplayData(): string {
        return "§c" . $this->playerName . ($this->killerName === null ? " §8died" : " §8got killed by §b" . $this->killerName);
    }

    public function serialize(BinaryStream $stream): void {
        $stream->putString($this->playerName);
        $stream->putInt($this->playerId);
        $stream->putString($this->killerName ?? "");
    }

    public function deserialize(BinaryStream $stream): void {
        $this->playerName = $stream->getString();
        $this->playerId = $stream->getInt();
        $name = $stream->getString();
        $this->killerName = empty($name) ? null : $name;
    }

    public function handle(ReplayPlayer $replay): void {
        $entity = $replay->getEntity($this->playerId);
        if ($entity !== null) {
            $replay->despawnEntity($this->playerId);
        }
    }
}
