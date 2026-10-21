<?php

declare(strict_types=1);

namespace bitpvp\replay\log;

use bitpvp\replay\log\type\PlayerDeathEventLog;
use pocketmine\utils\SingletonTrait;

final class EventLogHandler {
    use SingletonTrait {
        setInstance as private;
        reset as private;
    }

    /** @var array<int, EventLog> */
    private array $eventLogs = [];

    private function __construct() {
        $this->registerEventLog(
            new PlayerDeathEventLog()
        );
    }

    public function registerEventLog(EventLog ...$eventLogs): void {
        foreach ($eventLogs as $event) {
            $this->eventLogs[$event::id()] = clone $event;
        }
    }

    /**
     * @return array<int, EventLog>
     */
    public function getEventLogs(): array {
        return $this->eventLogs;
    }

    public function getEventLog(int $id): ?EventLog {
        return isset($this->eventLogs[$id]) ? clone $this->eventLogs[$id] : null;
    }
}
