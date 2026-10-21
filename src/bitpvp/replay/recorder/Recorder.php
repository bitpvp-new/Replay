<?php

declare(strict_types=1);

namespace bitpvp\replay\recorder;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\type\actor\ActorDespawnAction;
use bitpvp\replay\action\type\entity\EntitySpawnAction;
use bitpvp\replay\action\type\entity\EntitySpawnItemAction;
use bitpvp\replay\action\type\entity\EntityMoveAction;
use bitpvp\replay\action\type\entity\EntityEffectAction;
use bitpvp\replay\action\type\entity\EntityInventoryAction;
use bitpvp\replay\action\type\entity\EntitySpawnLivingAction;
use bitpvp\replay\action\type\world\WorldChangeAction;
use bitpvp\replay\action\type\world\WorldChangeTimeAction;
use bitpvp\replay\data\ReplayData;
use bitpvp\replay\data\ReplayInformation;
use bitpvp\replay\data\WorldData;
use bitpvp\replay\listener\record\RecordChunkListener;
use bitpvp\replay\listener\record\RecordInventoryListener;
use bitpvp\replay\listener\record\RecordListener;
use bitpvp\replay\log\EventLog;
use bitpvp\replay\ReplayAPI;
use bitpvp\replay\thread\packet\SaveReplayPacket;
use bitpvp\replay\thread\ReplayThread;
use bitpvp\replay\utils\ReplayTimings;
use DateTime;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\HandlerListManager;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

class Recorder {

    private RecordListener $listener;
    private RecordChunkListener $chunkListener;
    private DateTime $timestamp;
    private World $world;
    private TaskHandler $tickTask;

    /** @var array<string, WorldData> */
    private array $worldDatum = [];
    /** @var array<int, Action[]> */
    private array $actions = [];
    /** @var array<int, EventLog[]> */
    private array $eventLogs = [];
    private int $tick = 0;
    private int $currentTime = 0;
    /** @var array<int, Location> */
    private array $entityLocations = [];

    public function __construct(
        private RecordSettings $settings,
        private ReplayThread $thread,
        World $world
    ) {
        $this->timestamp = new DateTime();
        $this->listener = new RecordListener($this);
        $this->chunkListener = new RecordChunkListener($this);
        $this->changeWorld($world);

        $this->tickTask = $settings->getPlugin()->getScheduler()->scheduleRepeatingTask(
            new ClosureTask($this->tick(...)),
            $settings->getRecordInterval()
        );

        $world->getServer()->getPluginManager()->registerEvents(
            $this->listener,
            $settings->getPlugin()
        );
    }

    /**
     * @param \Closure(string $replayId, bool $success, string $pathOrError): void $onSaved
     */
    public function end(string $replayId, \Closure $onSaved): void {
        $this->tickTask->cancel();
        $this->world->unregisterChunkListenerFromAll($this->chunkListener);

        foreach ($this->world->getEntities() as $entity) {
            if ($entity instanceof Living) {
                RecordInventoryListener::removeListeners($entity, $this);
            }
        }
        HandlerListManager::global()->unregisterAll($this->listener);

        $information = ReplayInformation::create($replayId, $this->timestamp, $this->tick);
        $storage = $this->settings->getStorage() ?? ReplayAPI::getStorage();
        ReplayData::create($information, $this->actions, $this->eventLogs, $this->worldDatum)
            ->encodeAsync($this->settings->getPlugin(), $this->settings->getSerializationBatchSize())
            ->onCompletion(
                function(string $rawBuffer) use ($storage, $information, $onSaved, $replayId): void {
                    if ($storage !== null) {
                        ReplayAPI::compressInBackground($rawBuffer, function(string $compressed) use ($storage, $information, $onSaved, $replayId): void {
                            $storage->save(
                                $information,
                                $compressed,
                                function(string $id) use ($onSaved): void {
                                    $onSaved($id, true, "Saved to storage");
                                },
                                function(\Throwable $err) use ($onSaved, $replayId): void {
                                    $onSaved($replayId, false, $err->getMessage());
                                }
                            );
                        }, function(\Throwable $err) use ($onSaved, $replayId): void {
                            $onSaved($replayId, false, $err->getMessage());
                        });
                        return;
                    }

                    $this->thread->submit(new SaveReplayPacket(
                        $replayId,
                        $this->settings->getSavePath() . DIRECTORY_SEPARATOR . $replayId . ".replay",
                        $rawBuffer
                    ));
                },
                static function() use ($onSaved, $replayId): void {
                    $onSaved($replayId, false, "Replay encoding task was cancelled or failed; see the server log");
                }
            );
    }

    public function changeWorld(World $world): void {
        if (isset($this->world)) {
            if ($this->world === $world) {
                throw new \LogicException("Cannot change to the same world");
            }
            foreach ($this->world->getEntities() as $entity) {
                $this->removeEntity($entity);
            }
            $this->world->unregisterChunkListenerFromAll($this->chunkListener);
            $this->addAction(WorldChangeAction::create($world->getFolderName()));
        }

        $this->world = $world;
        $this->currentTime = $world->getTime();
        $name = $world->getFolderName();
        $this->worldDatum[$name] ??= WorldData::create($world);

        foreach ($world->getLoadedChunks() as $chunkHash => $chunk) {
            $this->addChunk($world, $chunk, $chunkHash);
        }
        foreach ($world->getEntities() as $entity) {
            if ($this->canRecordEntity($entity)) {
                $this->addEntity($entity);
            }
        }
    }

    public function getWorld(): World {
        return $this->world;
    }

    public function getSettings(): RecordSettings {
        return $this->settings;
    }

    private function tick(): void {
        $timings = ReplayTimings::recordingTick();
        $timings->startTiming();
        try {
            $this->tick += $this->settings->getRecordInterval();
            foreach ($this->world->getEntities() as $entity) {
                if ($this->canRecordEntity($entity)) {
                    $this->recordEntityMovement($entity);
                }
            }
            $time = $this->world->getTime();
            if ($this->currentTime !== $time) {
                $this->addAction(WorldChangeTimeAction::create($time));
                $this->currentTime = $time;
            }
        } finally {
            $timings->stopTiming();
        }
    }

    public function addEventLog(EventLog $eventLog): void {
        $this->eventLogs[max(0, $this->tick)][] = $eventLog;
    }

    public function addAction(Action $action): void {
        $this->actions[$this->tick][] = $action;
    }

    public function addChunk(World $world, Chunk $chunk, int $chunkHash): void {
        $name = $world->getFolderName();
        if (!isset($this->worldDatum[$name])) {
            $this->worldDatum[$name] = WorldData::create($world);
        }
        if (!$this->settings->shouldRecordWorldSnapshot()) {
            return;
        }
        $this->worldDatum[$name]->addChunk($chunk, $chunkHash);
        $world->registerChunkListener($this->chunkListener, ...self::xzFromHash($chunkHash));
    }

    public function addEntity(Entity $entity): void {
        if ($entity instanceof ItemEntity) {
            $this->addAction(EntitySpawnItemAction::create($entity));
        } elseif ($entity instanceof Living && !$entity instanceof Human) {
            $this->addAction(EntitySpawnLivingAction::create($entity));
        } else {
            $this->addAction(EntitySpawnAction::create($entity));
        }
        $this->entityLocations[$entity->getId()] = clone $entity->getLocation();
        if ($entity instanceof Living) {
            $this->addAction(EntityInventoryAction::create($entity));
            foreach ($entity->getEffects()->all() as $effect) {
                $effectAction = EntityEffectAction::create($entity->getId(), $effect);
                if ($effectAction !== null) {
                    $this->addAction($effectAction);
                }
            }
            RecordInventoryListener::addListener($this, $entity);
        }
    }

    public function removeEntity(Entity $entity): void {
        $this->addAction(ActorDespawnAction::create($entity->getId()));
        unset($this->entityLocations[$entity->getId()]);
        if ($entity instanceof Living) {
            RecordInventoryListener::removeListeners($entity, $this);
        }
    }

    public function recordEntityMovement(Entity $entity, ?Location $location = null, bool $teleport = false): void {
        if (!$this->canRecordEntity($entity)) {
            return;
        }
        $location ??= $entity->getLocation();
        $previous = $this->entityLocations[$entity->getId()] ?? null;
        if ($previous !== null && $previous->getWorld() === $location->getWorld()
            && $previous->x === $location->x && $previous->y === $location->y && $previous->z === $location->z
            && $previous->yaw === $location->yaw && $previous->pitch === $location->pitch) {
            return;
        }
        $this->addAction(EntityMoveAction::create($entity->getId(), clone $location, $teleport));
        $this->entityLocations[$entity->getId()] = clone $location;
    }

    public function canRecordEntity(Entity $entity): bool {
        return $entity->getWorld() === $this->world
            && (!$entity instanceof Player || !$entity->isSpectator());
    }

    /** @return array{int, int} [chunkX, chunkZ] */
    private static function xzFromHash(int $hash): array {
        World::getXZ($hash, $x, $z);
        return [$x, $z];
    }
}
