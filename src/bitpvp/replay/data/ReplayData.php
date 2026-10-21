<?php

declare(strict_types=1);

namespace bitpvp\replay\data;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\ActionHandler;
use bitpvp\replay\log\EventLog;
use bitpvp\replay\log\EventLogHandler;
use bitpvp\replay\ReplayAPI;
use bitpvp\replay\utils\BinaryStream;
use bitpvp\replay\utils\ReplayTimings;
use Generator;
use pocketmine\plugin\Plugin;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\timings\TimingsHandler;
use Throwable;

class ReplayData {

    private function __construct() {}

    private ReplayInformation $information;
    /** @var array<int, Action[]> */
    private array $actions = [];
    /** @var array<int, EventLog[]> */
    private array $eventLogs = [];
    /** @var array<string, WorldData> */
    private array $worldDatum = [];

    public static function create(ReplayInformation $information, array $actions, array $eventLogs, array $worldDatum): self {
        $data = new self();
        $data->information = $information;
        $data->actions = $actions;
        $data->eventLogs = $eventLogs;
        $data->worldDatum = $worldDatum;
        return $data;
    }

    public function getInformation(): ReplayInformation {
        return $this->information;
    }

    /**
     * @return array<int, Action[]>
     */
    public function getActions(): array {
        return $this->actions;
    }

    /**
     * @return Action[]
     */
    public function getActionsByTick(int $tick): array {
        return $this->actions[$tick] ?? [];
    }

    /**
     * @return array<int, EventLog[]>
     */
    public function getEventLogs(): array {
        return $this->eventLogs;
    }

    /** @return EventLog[] */
    public function getEventLogsByTick(int $tick): array {
        return $this->eventLogs[$tick] ?? [];
    }

    /**
     * @return array<string, WorldData>
     */
    public function getWorldDatum(): array {
        return $this->worldDatum;
    }

    public function getWorldData(string $worldName): ?WorldData {
        return $this->worldDatum[$worldName] ?? null;
    }

    /**
     * @return Promise<string>
     */
    public function encodeAsync(Plugin $plugin, int $itemsPerTick = 500): Promise {
        return self::runAsync($plugin, $this->encodeSteps(), $itemsPerTick, ReplayTimings::dataEncode());
    }

    /** @return Generator<mixed, null, mixed, string> */
    private function encodeSteps(): Generator {
        $stream = BinaryStream::encoder();

        $stream->putInt(count($this->actions));
        foreach ($this->actions as $tick => $actions) {
            $stream->putInt($tick);
            $stream->putInt(count($actions));
            foreach ($actions as $action) {
                $stream->putInt($action::id());
                $action->serialize($stream);
                yield null;
            }
            yield null;
        }

        $stream->putInt(count($this->eventLogs));
        foreach ($this->eventLogs as $tick => $eventLogs) {
            $stream->putInt($tick);
            $stream->putInt(count($eventLogs));
            foreach ($eventLogs as $eventLog) {
                $stream->putInt($eventLog::id());
                $eventLog->serialize($stream);
                yield null;
            }
            yield null;
        }

        $stream->putInt(count($this->worldDatum));
        foreach ($this->worldDatum as $worldName => $data) {
            $stream->putString($worldName);
            foreach ($data->encodeSteps($stream) as $_) {
                yield null;
            }
        }

        return $stream->getBuffer();
    }

    /**
     * @return Promise<ReplayData>
     */
    public static function decodeAsync(Plugin $plugin, ReplayInformation $information, string $buffer, int $itemsPerTick = 500): Promise {
        return self::runAsync($plugin, self::decodeSteps($information, $buffer), $itemsPerTick, ReplayTimings::dataDecode());
    }

    /** @return Generator<mixed, null, mixed, self> */
    private static function decodeSteps(ReplayInformation $information, string $buffer): Generator {
        $stream = BinaryStream::decoder($buffer, 0);
        $actionHandler = ActionHandler::getInstance();

        $actions = [];
        $tickCount = $stream->getInt();
        for ($i = 0; $i < $tickCount; $i++) {
            $tick = $stream->getInt();
            $actionCount = $stream->getInt();
            for ($j = 0; $j < $actionCount; $j++) {
                $actionId = $stream->getInt();
                $action = $actionHandler->getAction($actionId);
                if ($action !== null) {
                    $action->deserialize($stream);
                    $actions[$tick][] = $action;
                }
                yield null;
            }
            yield null;
        }

        $logHandler = EventLogHandler::getInstance();
        $eventLogs = [];
        $logTickCount = $stream->getInt();
        for ($i = 0; $i < $logTickCount; $i++) {
            $tick = $stream->getInt();
            $logCount = $stream->getInt();
            for ($j = 0; $j < $logCount; $j++) {
                $logId = $stream->getInt();
                $event = $logHandler->getEventLog($logId);
                if ($event !== null) {
                    $event->deserialize($stream);
                    $eventLogs[$tick][] = $event;
                }
                yield null;
            }
            yield null;
        }

        $worldDatum = [];
        $worldDataCount = $stream->getInt();
        for ($i = 0; $i < $worldDataCount; $i++) {
            $worldName = $stream->getString();
            $worldDatum[$worldName] = yield from WorldData::decodeSteps($stream);
        }

        return self::create($information, $actions, $eventLogs, $worldDatum);
    }

    /**
     * @param Generator<mixed, null, mixed, mixed> $steps
     * @return Promise<mixed>
     */
    private static function runAsync(Plugin $plugin, Generator $steps, int $itemsPerTick, TimingsHandler $timings): Promise {
        if ($itemsPerTick < 1) {
            throw new \InvalidArgumentException("Serialization batch size must be at least one");
        }

        $resolver = new PromiseResolver();
        $task = new ReplayDataTask(
            $steps,
            $itemsPerTick,
            $timings,
            $resolver,
            static function(Throwable $error) use ($plugin): void {
                $message = "Replay data serialization failed: " . $error->getMessage();
                try {
                    ReplayAPI::getLogger()->error($message);
                } catch (Throwable) {
                    $plugin->getLogger()->error($message);
                }
            }
        );
        $plugin->getScheduler()->scheduleRepeatingTask($task, 1);
        return $resolver->getPromise();
    }
}
