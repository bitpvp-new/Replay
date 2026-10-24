<?php

declare(strict_types=1);

namespace bitpvp\replay;

use bitpvp\replay\action\ActionHandler;
use bitpvp\replay\data\ReplayData;
use bitpvp\replay\data\ReplayInformation;
use bitpvp\replay\log\EventLogHandler;
use bitpvp\replay\recorder\Recorder;
use bitpvp\replay\recorder\RecordSettings;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\replayer\ReplaySettings;
use bitpvp\replay\replayer\util\ReplayGenerator;
use bitpvp\replay\storage\ReplayStorage;
use bitpvp\replay\thread\packet\CompressPacket;
use bitpvp\replay\thread\packet\DecompressPacket;
use bitpvp\replay\thread\packet\LoadReplayPacket;
use bitpvp\replay\thread\ReplayThread;
use bitpvp\replay\thread\result\CompressResultPacket;
use bitpvp\replay\thread\result\DecompressResultPacket;
use bitpvp\replay\thread\result\DeleteStorageFileResultPacket;
use bitpvp\replay\thread\result\FetchAllJsonStorageResultPacket;
use bitpvp\replay\thread\result\LoadJsonStorageResultPacket;
use bitpvp\replay\thread\result\LoadReplayResultPacket;
use bitpvp\replay\thread\result\SaveJsonStorageResultPacket;
use bitpvp\replay\thread\result\SaveReplayResultPacket;
use bitpvp\task\TaskManager;
use LogicException;
use pocketmine\event\EventPriority;
use pocketmine\event\plugin\PluginDisableEvent;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\World;
use PrefixedLogger;
use RuntimeException;

/**
 * Main static entry point for the replay library.
 */
final class ReplayAPI {

    private static ?Plugin $plugin = null;
    private static ?PrefixedLogger $logger = null;
    private static ?ReplayThread $thread = null;
    private static ?int $notifierId = null;
    private static ?ReplayStorage $storage = null;

    /** @var array<string, Recorder> */
    private static array $recorders = [];

    /** @var array<string, ReplayPlayer> */
    private static array $replayPlayers = [];

    /** @var array<string, \Closure> */
    private static array $saveCallbacks = [];

    /** @var array<string, array{ReplaySettings, Player, ReplayInformation, \Closure|null}> */
    private static array $loadCallbacks = [];

    /** @var array<string, array{\Closure, (\Closure|null)}> */
    private static array $storageSaveCallbacks = [];

    /** @var array<string, array{\Closure, (\Closure|null)}> */
    private static array $storageLoadCallbacks = [];

    /** @var array<string, array{\Closure, (\Closure|null)}> */
    private static array $storageDeleteCallbacks = [];

    /** @var array<string, array{\Closure, (\Closure|null)}> */
    private static array $storageFetchAllCallbacks = [];

    /** @var array<string, array{\Closure, (\Closure|null)}> */
    private static array $compressCallbacks = [];

    /** @var array<string, array{\Closure, (\Closure|null)}> */
    private static array $decompressCallbacks = [];

    private function __construct() {}

    public static function register(PluginBase $plugin): void {
        if (self::$plugin !== null) {
            throw new LogicException("ReplayAPI is already registered");
        }

        if(!TaskManager::isRegistered()){
            TaskManager::register($plugin);
        }

        self::$plugin = $plugin;
        self::$logger = new PrefixedLogger($plugin->getLogger(), "Replay");

        ActionHandler::getInstance();
        EventLogHandler::getInstance();

        GeneratorManager::getInstance()->addGenerator(ReplayGenerator::class, "replay", fn() => null, true);

        $notifier = $plugin->getServer()->getTickSleeper()->addNotifier(self::collectThreadResults(...));
        self::$notifierId = $notifier->getNotifierId();

        self::$thread = new ReplayThread($notifier);
        self::$thread->start();
        self::getLogger()->info("Replay API initialized");
        self::getLogger()->debug("Background worker started");

        $plugin->getServer()->getPluginManager()->registerEvent(PluginDisableEvent::class, function(PluginDisableEvent $event): void {
            if($event->getPlugin() === self::$plugin){
                self::shutdown();
            }
        }, EventPriority::MONITOR, $plugin);
    }

    public static function shutdown(): void {
        self::$logger?->info("Shutting down Replay API");
        foreach (self::$replayPlayers as $replayPlayer) {
            $replayPlayer->end();
        }
        self::$replayPlayers = [];
        self::$thread?->quit();
        self::$thread = null;
        self::$notifierId === null || Server::getInstance()->getTickSleeper()->removeNotifier(self::$notifierId);
        self::$notifierId = null;
        self::$storage?->close();
        self::$storage = null;
    }

    public static function getLogger(): PrefixedLogger {
        self::assertRegistered();
        return self::$logger;
    }

    public static function setStorage(?ReplayStorage $storage): void {
        self::$storage = $storage;
    }

    public static function getStorage(): ?ReplayStorage {
        return self::$storage;
    }

    public static function getThread(): ReplayThread {
        self::assertRegistered();
        return self::$thread;
    }

    /**
     * @param \Closure(string $replayId, bool $success, string $pathOrError): void $onSaved
     */
    public static function startRecording(
        RecordSettings $settings,
        World $world,
        \Closure $onSaved
    ): string {
        self::assertRegistered();
        if ($settings->getStorage() === null && self::$storage === null && $settings->getSavePath() === "") {
            throw new LogicException("A save path or ReplayStorage is required to record a replay");
        }

        $replayId = ReplayInformation::generateIdentifier();
        self::getLogger()->info("Started recording replay $replayId in world " . $world->getFolderName());
        self::getLogger()->debug("Recording interval: " . $settings->getRecordInterval() . " tick(s)");
        self::$saveCallbacks[$replayId] = static function(string $id, bool $success, string $message) use ($onSaved, $replayId): void {
            unset(self::$saveCallbacks[$replayId]);
            if ($success) {
                self::getLogger()->info("Saved replay $id: $message");
            } else {
                self::getLogger()->error("Failed to save replay $id: $message");
            }
            $onSaved($id, $success, $message);
        };
        self::$recorders[$replayId] = new Recorder($settings, self::$thread, $world);

        return $replayId;
    }

    public static function stopRecording(string $replayId): void {
        self::assertRegistered();

        $recorder = self::$recorders[$replayId] ?? null;
        if ($recorder === null) {
            return;
        }
        self::getLogger()->info("Stopped recording replay $replayId; saving data");
        $recorder->end($replayId, self::$saveCallbacks[$replayId] ?? static function (): void {});
        unset(self::$recorders[$replayId]);
    }

    public static function isRecording(string $replayId): bool {
        return isset(self::$recorders[$replayId]);
    }

    public static function getRecorder(string $replayId): ?Recorder {
        return self::$recorders[$replayId] ?? null;
    }

    /**
     * @param \Closure(bool $success, ?ReplayPlayer $player, string $error): void|null $onReady
     */
    public static function startReplay(
        ReplaySettings $settings,
        Player $viewer,
        ReplayInformation $information,
        string $filePath,
        ?\Closure $onReady = null
    ): void {
        self::assertRegistered();

        $replayId = $information->getIdentifier();
        self::getLogger()->debug("Loading replay $replayId from file");
        self::$loadCallbacks[$replayId] = [$settings, $viewer, $information, $onReady];
        self::$thread->submit(new LoadReplayPacket($replayId, $filePath));
    }

    /**
     * @param \Closure(bool $success, ReplayPlayer|null $player , string $error): void|null $onReady
     */
    public static function startReplayFromStorage(
        ReplaySettings $settings,
        Player $viewer,
        string $replayId,
        ?ReplayStorage $storage = null,
        ?\Closure $onReady = null
    ): void {
        self::assertRegistered();

        $store = $storage ?? self::$storage;
        if ($store === null) {
            throw new LogicException("No ReplayStorage provided and none registered globally");
        }

        self::getLogger()->debug("Loading replay $replayId from storage");

        $store->load(
            $replayId,
            function(ReplayInformation $information, string $rawBuffer) use ($settings, $viewer, $onReady, $replayId): void {
                ReplayData::decodeAsync($settings->getPlugin(), $information, $rawBuffer, $settings->getSerializationBatchSize())
                    ->onCompletion(
                        function(ReplayData $replayData) use ($settings, $viewer, $onReady, $replayId): void {
                            try {
                                $uuid = $viewer->getUniqueId()->toString();
                                $replayPlayer = self::createReplayPlayer($settings, $viewer, $replayData, $uuid);
                            } catch (\Throwable $error) {
                                self::getLogger()->error("Failed to prepare replay $replayId: " . $error->getMessage());
                                if ($onReady !== null) {
                                    $onReady(false, null, $error->getMessage());
                                }
                                return;
                            }

                            if ($onReady !== null) {
                                $onReady(true, $replayPlayer, "");
                            }
                        },
                        static function() use ($onReady): void {
                            if ($onReady !== null) {
                                $onReady(false, null, "Replay decoding task was cancelled or failed; see the server log");
                            }
                        }
                    );
            },
            function(\Throwable $error) use ($onReady, $replayId): void {
                self::getLogger()->error("Failed to load replay $replayId from storage: " . $error->getMessage());
                if ($onReady !== null) {
                    $onReady(false, null, $error->getMessage());
                }
            }
        );
    }

    public static function stopReplay(Player $viewer): void {
        $uuid = $viewer->getUniqueId()->toString();
        $player = self::$replayPlayers[$uuid] ?? null;
        if ($player === null) {
            return;
        }
        self::getLogger()->info("Stopped replay for " . $viewer->getName());
        $player->end();
        unset(self::$replayPlayers[$uuid]);
    }

    public static function isWatching(Player $viewer): bool {
        return isset(self::$replayPlayers[$viewer->getUniqueId()->toString()]);
    }

    public static function getReplayPlayer(Player $viewer): ?ReplayPlayer {
        return self::$replayPlayers[$viewer->getUniqueId()->toString()] ?? null;
    }

    public static function registerStorageSaveCallback(string $id, \Closure $onSuccess, ?\Closure $onError = null): void {
        self::$storageSaveCallbacks[$id] = [$onSuccess, $onError];
    }

    public static function registerStorageLoadCallback(string $id, \Closure $onSuccess, ?\Closure $onError = null): void {
        self::$storageLoadCallbacks[$id] = [$onSuccess, $onError];
    }

    public static function registerStorageDeleteCallback(string $token, \Closure $onSuccess, ?\Closure $onError = null): void {
        self::$storageDeleteCallbacks[$token] = [$onSuccess, $onError];
    }

    public static function registerStorageFetchAllCallback(string $token, \Closure $onSuccess, ?\Closure $onError = null): void {
        self::$storageFetchAllCallbacks[$token] = [$onSuccess, $onError];
    }

    public static function compressInBackground(string $rawBuffer, \Closure $onSuccess, ?\Closure $onError = null): void {
        self::assertRegistered();
        $token = uniqid("comp_");
        self::$compressCallbacks[$token] = [$onSuccess, $onError];
        self::$thread->submit(new CompressPacket($token, $rawBuffer));
    }

    public static function decompressInBackground(string $compressed, \Closure $onSuccess, ?\Closure $onError = null): void {
        self::assertRegistered();
        $token = uniqid("decomp_");
        self::$decompressCallbacks[$token] = [$onSuccess, $onError];
        self::$thread->submit(new DecompressPacket($token, $compressed));
    }

    private static function collectThreadResults(): void {
        if (self::$thread === null) {
            return;
        }

        foreach (self::$thread->collectResults() as $packet) {
            if ($packet instanceof SaveReplayResultPacket) {
                $cb = self::$saveCallbacks[$packet->getReplayId()] ?? null;
                unset(self::$saveCallbacks[$packet->getReplayId()]);
                if ($cb !== null) {
                    $cb($packet->getReplayId(), $packet->isSuccess(), $packet->getMessage());
                }
            } elseif ($packet instanceof LoadReplayResultPacket) {
                $id = $packet->getReplayId();
                [$settings, $viewer, $information, $onReady] = self::$loadCallbacks[$id] ?? [null, null, null, null];
                unset(self::$loadCallbacks[$id]);

                if ($settings === null) {
                    continue;
                }

                if (!$packet->isSuccess()) {
                    self::getLogger()->error("Failed to load replay $id: " . ($packet->getError() ?? "Unknown error"));
                    if ($onReady !== null) {
                        $onReady(false, null, $packet->getError() ?? "Unknown error");
                    }
                    continue;
                }

                $rawBuffer = $packet->getRawBuffer();
                if ($rawBuffer === null) {
                    $error = "Loaded replay has no data";
                    if ($onReady !== null) {
                        $onReady(false, null, $error);
                    }
                    continue;
                }

                ReplayData::decodeAsync($settings->getPlugin(), $information, $rawBuffer, $settings->getSerializationBatchSize())
                    ->onCompletion(
                        function(ReplayData $replayData) use ($settings, $viewer, $onReady, $id): void {
                            try {
                                $uuid = $viewer->getUniqueId()->toString();
                                $replayPlayer = self::createReplayPlayer($settings, $viewer, $replayData, $uuid);
                            } catch (\Throwable $error) {
                                self::getLogger()->error("Failed to prepare replay $id: " . $error->getMessage());
                                if ($onReady !== null) {
                                    $onReady(false, null, $error->getMessage());
                                }
                                return;
                            }

                            if ($onReady !== null) {
                                $onReady(true, $replayPlayer, "");
                            }
                        },
                        static function() use ($onReady, $id): void {
                            self::getLogger()->error("Failed to decode replay $id; decoding task was cancelled or failed");
                            if ($onReady !== null) {
                                $onReady(false, null, "Replay decoding task was cancelled or failed; see the server log");
                            }
                        }
                    );
            } elseif ($packet instanceof SaveJsonStorageResultPacket) {
                $id = $packet->getReplayId();
                [$onSuccess, $onError] = self::$storageSaveCallbacks[$id] ?? [null, null];
                unset(self::$storageSaveCallbacks[$id]);

                if ($packet->isSuccess()) {
                    if ($onSuccess !== null) {
                        $onSuccess($id);
                    }
                } else {
                    $err = new RuntimeException($packet->getMessage());
                    $onError !== null ? $onError($err) : throw $err;
                }
            } elseif ($packet instanceof LoadJsonStorageResultPacket) {
                $id = $packet->getReplayId();
                [$onSuccess, $onError] = self::$storageLoadCallbacks[$id] ?? [null, null];
                unset(self::$storageLoadCallbacks[$id]);

                if ($packet->isSuccess()) {
                    if ($onSuccess !== null) {
                        $onSuccess($packet->getJsonRaw(), (string)$packet->getRawBuffer());
                    }
                } else {
                    $err = new RuntimeException($packet->getError() ?? "Failed to load replay storage");
                    $onError !== null ? $onError($err) : throw $err;
                }
            } elseif ($packet instanceof FetchAllJsonStorageResultPacket) {
                $token = $packet->getToken();
                [$onSuccess, $onError] = self::$storageFetchAllCallbacks[$token] ?? [null, null];
                unset(self::$storageFetchAllCallbacks[$token]);

                if ($packet->isSuccess()) {
                    if ($onSuccess !== null) {
                        $onSuccess($packet->getItemsJson());
                    }
                } else {
                    $err = new RuntimeException($packet->getError() ?? "Failed to fetch all replays");
                    $onError !== null ? $onError($err) : throw $err;
                }
            } elseif ($packet instanceof DeleteStorageFileResultPacket) {
                $token = $packet->getToken();
                [$onSuccess, $onError] = self::$storageDeleteCallbacks[$token] ?? [null, null];
                unset(self::$storageDeleteCallbacks[$token]);

                if ($packet->isSuccess()) {
                    if ($onSuccess !== null) {
                        $onSuccess();
                    }
                } else {
                    $err = new RuntimeException($packet->getError() ?? "Failed to delete storage file");
                    $onError !== null ? $onError($err) : throw $err;
                }
            } elseif ($packet instanceof CompressResultPacket) {
                $token = $packet->getToken();
                [$onSuccess, $onError] = self::$compressCallbacks[$token] ?? [null, null];
                unset(self::$compressCallbacks[$token]);

                if ($packet->isSuccess()) {
                    if ($onSuccess !== null) {
                        $onSuccess($packet->getCompressed());
                    }
                } else {
                    $err = new RuntimeException($packet->getError() ?? "Compression failed");
                    $onError !== null ? $onError($err) : throw $err;
                }
            } elseif ($packet instanceof DecompressResultPacket) {
                $token = $packet->getToken();
                [$onSuccess, $onError] = self::$decompressCallbacks[$token] ?? [null, null];
                unset(self::$decompressCallbacks[$token]);

                if ($packet->isSuccess()) {
                    if ($onSuccess !== null) {
                        $onSuccess($packet->getRawBuffer());
                    }
                } else {
                    $err = new RuntimeException($packet->getError() ?? "Decompression failed");
                    $onError !== null ? $onError($err) : throw $err;
                }
            }
        }
    }

    private static function assertRegistered(): void {
        if (self::$plugin === null || self::$thread === null) {
            throw new LogicException("ReplayAPI is not registered. Call ReplayAPI::register(\$plugin) in onEnable().");
        }
    }

    private static function createReplayPlayer(ReplaySettings $settings, Player $viewer, ReplayData $data, string $uuid): ReplayPlayer {
        if (isset(self::$replayPlayers[$uuid])) {
            self::$replayPlayers[$uuid]->end();
        }
        $replayPlayer = new ReplayPlayer($settings, $viewer, $data, static function(ReplayPlayer $ended) use ($uuid): void {
            if ((self::$replayPlayers[$uuid] ?? null) === $ended) {
                unset(self::$replayPlayers[$uuid]);
            }
            self::getLogger()->debug("Replay ended for " . $ended->getViewer()->getName());
        });
        self::$replayPlayers[$uuid] = $replayPlayer;
        self::getLogger()->info("Started replay for " . $viewer->getName() . " (" . $data->getInformation()->getIdentifier() . ")");
        return $replayPlayer;
    }

    public static function isRegistered(): bool {
        return self::$plugin !== null;
    }
}
