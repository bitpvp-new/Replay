<?php

declare(strict_types=1);

namespace bitpvp\replay\replayer;

use bitpvp\replay\action\Action;
use bitpvp\replay\data\ReplayData;
use bitpvp\replay\data\WorldData;
use bitpvp\replay\entity\ReplayEntity;
use bitpvp\replay\entity\type\ReplayItemEntity;
use bitpvp\replay\replayer\event\ReplayChangeDirectionEvent;
use bitpvp\replay\replayer\event\ReplayEndEvent;
use bitpvp\replay\replayer\event\ReplayRestartEvent;
use bitpvp\replay\replayer\event\ReplayStartEvent;
use bitpvp\replay\replayer\event\ReplayTogglePauseEvent;
use bitpvp\replay\replayer\util\ReplayGenerator;
use bitpvp\replay\replayer\util\ReplayPlayDirection;
use bitpvp\replay\utils\ReplaySkipTickTask;
use bitpvp\replay\utils\ReplayTimings;
use bitpvp\task\TaskCancelException;
use bitpvp\task\TaskManager;
use pocketmine\block\tile\TileFactory;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;

class ReplayPlayer {

    private TaskHandler $task;
    private bool $paused = true;
    private int $currentTick = 0;
    private float $speedTicks = 0.0;
    private float $speed;
    private ReplayPlayDirection $playDirection;
    private ?World $world = null;
    private bool $ended = false;

    /** @var array<int, ReplayEntity> */
    private array $entities = [];
    /** @var array<int, Action[]> */
    private array $reversed = [];

    private int $duration;

    public function __construct(
        private ReplaySettings $settings,
        private Player $viewer,
        private ReplayData $data,
        private ?\Closure $onEnd = null
    ) {
        $this->speed = $settings->getSpeed();
        $this->playDirection = ReplayPlayDirection::FORWARDS;
        $this->duration = $data->getInformation()->getDuration();
        $this->switchWorld(array_key_first($this->data->getWorldDatum()));

        TaskManager::create()
            ->repeating(1)
            ->whenCalled(function(): void {
                !$this->ended || TaskCancelException::cancel();
                $this->speedTicks += $this->speed;
                if (fmod($this->speedTicks, 1) !== 0.0) {
                    return;
                }
                $times = (int)$this->speedTicks;
                for ($i = 0; $i < $times; $i++) {
                    if (!$this->tick()) {
                        break;
                    }
                }
                $this->speedTicks = 0;
            });

        (new ReplayStartEvent($this))->call();
    }

    private function tick(?ReplayPlayDirection $direction = null): bool {
        if ($this->paused && $direction === null) {
            return false;
        }

        return ReplayTimings::replayTick()->time(function () use ($direction): bool {
            return $this->processTick($direction);
        });
    }

    private function processTick(?ReplayPlayDirection $direction): bool {
        $reversed = ($direction ?? $this->playDirection) === ReplayPlayDirection::BACKWARDS;

        if ($reversed) {
            foreach (array_reverse($this->reversed[$this->currentTick] ?? []) as $action) {
                $action->handle($this);
            }
        } else {
            if (isset($this->reversed[$this->currentTick])) {
                foreach ($this->data->getActionsByTick($this->currentTick) as $action) {
                    $action->handle($this);
                }
            } else {
                foreach ($this->data->getActionsByTick($this->currentTick) as $action) {
                    if ($reversed2 = $action->handleReversed($this)) {
                        $this->reversed[$this->currentTick][] = $reversed2;
                    }
                    $action->handle($this);
                }
            }
            foreach ($this->data->getEventLogsByTick($this->currentTick) as $eventLog) {
                $eventLog->handle($this);
            }
        }

        $this->currentTick += $reversed ? -1 : 1;

        if ($this->currentTick < 0) {
            $this->currentTick = 0;
            if ($this->playDirection === ReplayPlayDirection::BACKWARDS) {
                $this->paused = true;
                $this->playDirection = ReplayPlayDirection::FORWARDS;
                (new ReplayRestartEvent($this, true))->call();
            }
            return false;
        }

        if ($this->currentTick >= $this->duration) {
            if (!$this->settings->isLoop()) {
                $this->end();
                return false;
            }

            $this->skip(ReplayPlayDirection::BACKWARDS, $this->currentTick + 1);
            $this->speedTicks = 0;
            (new ReplayRestartEvent($this))->call();
            return false;
        }

        // Display progress using the experience bar

        $this->viewer->getXpManager()->setXpAndProgress(
            (int)floor($this->currentTick / 20),
            $this->currentTick / $this->duration
        );
        return true;
    }

    public function end(): void {
        if ($this->ended) {
            return;
        }
        $this->ended = true;
        $this->removeCurrentWorld();
        (new ReplayEndEvent($this))->call();
        if ($this->onEnd !== null) {
            ($this->onEnd)($this);
        }
    }

    public function skip(ReplayPlayDirection $direction, int $ticks): void {
        if ($ticks < 0) {
            throw new \InvalidArgumentException("Ticks to skip cannot be negative");
        }

        $this->settings->getPlugin()->getScheduler()->scheduleRepeatingTask(
            new ReplaySkipTickTask(
                fn(int $_) => $this->tick($direction),
                $ticks,
                5,
                fn() => $this->speedTicks = 0
            ), 1
        );
    }

    public function skipToTick(int $tick): void {
        if ($tick === $this->currentTick) {
            return;
        }
        $this->skip(
            $tick > $this->currentTick ? ReplayPlayDirection::FORWARDS : ReplayPlayDirection::BACKWARDS,
            abs($tick - $this->currentTick)
        );
    }

    public function togglePaused(): bool {
        $paused = $this->paused = !$this->paused;
        (new ReplayTogglePauseEvent($this))->call();
        return $paused;
    }

    public function pause(): void {
        if (!$this->paused) {
            $this->togglePaused();
        }
    }

    public function unpause(): void {
        if ($this->paused) {
            $this->togglePaused();
        }
    }

    public function togglePause(): bool {
        return $this->togglePaused();
    }

    public function isPaused(): bool {
        return $this->paused;
    }

    public function getPlayDirection(): ReplayPlayDirection {
        return $this->playDirection;
    }

    public function setPlayDirection(ReplayPlayDirection $playDirection): void {
        if ($playDirection === $this->playDirection) {
            return;
        }
        $this->playDirection = $playDirection;
        (new ReplayChangeDirectionEvent($this))->call();
    }

    public function getSpeed(): float {
        return $this->speed;
    }

    public function setSpeed(float $speed): void {
        if (!is_finite($speed) || $speed <= 0.0) {
            throw new \InvalidArgumentException("Replay speed must be a finite number greater than zero");
        }
        $this->speedTicks = 0;
        $this->speed = $speed;
    }

    public function getCurrentTick(): int {
        return $this->currentTick;
    }

    public function getViewer(): Player {
        return $this->viewer;
    }

    public function getWorld(): ?World {
        return $this->world;
    }

    public function getSettings(): ReplaySettings {
        return $this->settings;
    }

    public function getData(): ReplayData {
        return $this->data;
    }

    /** @internal */
    public function spawnEntity(Entity $entity): void {
        if ($entity instanceof ReplayEntity) {
            $this->entities[$entity->getActualId()] = $entity;
        }
        $entity->spawnToAll();
    }

    /** @internal */
    public function despawnEntity(int $entityId): void {
        if (!isset($this->entities[$entityId])) {
            return;
        }
        if (!$this->entities[$entityId]->isClosed()) {
            $this->entities[$entityId]->flagForDespawn();
        }
        unset($this->entities[$entityId]);
    }

    public function getEntity(int $entityId): ?ReplayEntity {
        return $this->entities[$entityId] ?? null;
    }

    /**
     * @return array<int, ReplayEntity>
     */
    public function getEntities(): array {
        return $this->entities;
    }

    /**
     * @return array{contents: array<int, Item>, armor: array<int, Item>, offhand: Item, heldSlot: int}|null
     */
    public function getEntityInventory(int $entityId): ?array {
        $entity = $this->entities[$entityId] ?? null;
        if (!$entity instanceof Human) {
            return null;
        }
        return [
            "contents" => $entity->getInventory()->getContents(),
            "armor" => $entity->getArmorInventory()->getContents(),
            "offhand" => $entity->getOffHandInventory()->getItem(0),
            "heldSlot" => $entity->getInventory()->getHeldItemIndex(),
        ];
    }

    /** @internal */
    public function switchWorld(string $worldName): void {
        if ($this->world !== null) {
            $this->removeCurrentWorld();
        }

        $worldData = $this->data->getWorldData($worldName);
        if ($worldData === null) {
            return;
        }

        $id = uniqid('replay_');
        $server = $this->viewer->getServer();
        $server->getWorldManager()->generateWorld(
            $id,
            WorldCreationOptions::create()
                ->setGeneratorClass(ReplayGenerator::class)
                ->setSpawnPosition($worldData->getSpawn()),
            false
        );

        $world = $server->getWorldManager()->getWorldByName($id);
        if ($world === null) {
            return;
        }

        $this->world = $world;
        $world->setDisplayName($worldName);
        $world->setTime($worldData->getInitialTime());
        $world->stopTime();

        foreach ($worldData->getChunks() as $chunkHash => $chunk) {
            World::getXZ($chunkHash, $x, $z);
            $world->setChunk($x, $z, $chunk);
        }
        $factory = TileFactory::getInstance();
        foreach ($worldData->getTiles() as $tag) {
            $tile = $factory->createFromData($world, $tag);
            if ($tile !== null) {
                $world->addTile($tile);
            }
        }

        $this->viewer->teleport($this->getReplaySpawn($world, $worldData));
        $this->speedTicks = 0;
    }

    private function getReplaySpawn(World $world, WorldData $worldData): Position {
        $spawn = $worldData->getSpawn();
        $chunks = $worldData->getChunks();
        $spawnChunkHash = World::chunkHash($spawn->getFloorX() >> 4, $spawn->getFloorZ() >> 4);

        if (isset($chunks[$spawnChunkHash])) {
            return $world->getSafeSpawn($spawn);
        }

        if ($chunks !== []) {
            $firstChunkHash = array_key_first($chunks);
            World::getXZ($firstChunkHash, $chunkX, $chunkZ);
            $fallbackSpawn = new Vector3(
                ($chunkX << 4) + 8,
                $spawn->y,
                ($chunkZ << 4) + 8
            );
            $world->setSpawnLocation($fallbackSpawn);
            return $world->getSafeSpawn($fallbackSpawn);
        }

        // Replays without a world snapshot have no terrain for getSafeSpawn() to inspect.
        return Position::fromObject($spawn, $world);
    }

    private function removeCurrentWorld(): void {
        if ($this->world === null) {
            return;
        }
        $name = $this->world->getFolderName();
        foreach ($this->entities as $entity) {
            if (!$entity->isClosed()) {
                $entity->flagForDespawn();
            }
        }
        $this->entities = [];
        $manager = $this->world->getServer()->getWorldManager();
        if ($manager->isWorldLoaded($name)) {
            $manager->unloadWorld($this->world, false);
        }
        $this->world = null;
    }
}
