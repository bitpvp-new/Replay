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
use bitpvp\replay\utils\ReplayUtils;
use bitpvp\task\Task;
use bitpvp\task\TaskCancelException;
use bitpvp\task\TaskManager;
use Closure;
use DateTime;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\RegisteredListener;
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
    private bool $ended = false;

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

    /** @var array<string, RegisteredListener[]> */
    private array $eventEndingHandling = [];

    public function __construct(
        private RecordSettings $settings,
        private ReplayThread $thread,
        private string $identifier,
        World $world
    ) {
        $this->timestamp = new DateTime();
        $this->chunkListener = new RecordChunkListener($this);
        $this->changeWorld($world);
        $this->listener = new RecordListener($this);
        
        TaskManager::create()
            ->repeating($this->settings->getRecordInterval())
            ->whenCalled($this->tick(...));

        $world->getServer()->getPluginManager()->registerEvents(
            $this->listener,
            $settings->getPlugin()
        );
    }

    /**
     * @return string
     */
    public function getIdentifier(): string {
        return $this->identifier;
    }

    /**
     * @param Closure $event
     * @param int $priority
     * @param bool $handleCancelled
     * @return RegisteredListener
     * @throws \ReflectionException
     */
    public function addEventEndingHandler(Closure $event, int $priority = EventPriority::NORMAL, bool $handleCancelled = false): RegisteredListener {
        $eventClass = ReplayUtils::parseEventHandler($event);
        if(!isset($this->eventEndingHandling[$eventClass])) {
            $this->eventEndingHandling[$eventClass] = [];
        }

        return $this->eventEndingHandling[$eventClass][] = $this->world->getServer()->getPluginManager()->registerEvent(
            $eventClass,
            function(Event $parent) use ($event): void {
                if(!$event($parent)){
                    ReplayAPI::stopRecording($this->identifier);
                }
            },
            $priority,
            $this->settings->getPlugin(),
            $handleCancelled
        );
    }

    /**
     * @param Closure(string $replayId, bool $success, string $pathOrError): void $onSaved
     * @internal
     */
    public function end(Closure $onSaved): void {
        if($this->ended) return;
        $replayId = $this->identifier;
        $this->ended = true;
        $this->world->unregisterChunkListenerFromAll($this->chunkListener);

        foreach ($this->world->getEntities() as $entity) {
            if ($entity instanceof Living) {
                RecordInventoryListener::removeListeners($entity, $this);
            }
        }
        $manager = HandlerListManager::global();
        $manager->unregisterAll($this->listener);

        foreach ($this->eventEndingHandling as $class => $listeners) {
            foreach ($listeners as $listener) {
                $manager->getListFor($class)->unregister($listener);
            }
        }

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

    /**
     * @return void
     * @throws TaskCancelException
     */
    private function tick(): void {
        if($this->ended){
            TaskCancelException::cancel();
        }
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
        $world->registerChunkListener($this->chunkListener, ...ReplayUtils::xzFromHash($chunkHash));
    }

    public function removeChunk(World $world, Chunk $chunk, int $chunkHash): void {
        $name = $world->getFolderName();
        if(!isset($this->worldDatum[$name])) {
            $this->worldDatum[$name] = WorldData::create($world);
        }

        if(!$this->settings->shouldRecordWorldSnapshot()) {
            return;
        }

        $this->worldDatum[$name]->removeChunk($chunk, $chunkHash);
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
        if ($previous !== null && $previous->equals($location)) {
            return;
        }
        $this->addAction(EntityMoveAction::create($entity->getId(), clone $location, $teleport));
        $this->entityLocations[$entity->getId()] = clone $location;
    }

    public function canRecordEntity(Entity $entity): bool {
        return $entity->getWorld() === $this->world
            && (!$entity instanceof Player || !$entity->isSpectator());
    }
}
