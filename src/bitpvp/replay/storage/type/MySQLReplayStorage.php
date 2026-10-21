<?php

declare(strict_types=1);

namespace bitpvp\replay\storage\type;

use bitpvp\replay\data\ReplayInformation;
use bitpvp\replay\ReplayAPI;
use bitpvp\replay\storage\ReplayStorage;
use DateTime;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\AssumptionFailedError;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use RuntimeException;
use Throwable;

class MySQLReplayStorage implements ReplayStorage {

    public function __construct(
        private readonly DataConnector $connector
    ) {
        $this->init();
    }

    public static function create(Plugin $plugin, array $settings, string $sqlFile = "mysql.sql"): self {
        if(!$plugin instanceof PluginBase) throw new AssumptionFailedError();
        $connector = libasynql::create($plugin, $settings, [
            "mysql" => $sqlFile
        ]);
        return new self($connector);
    }

    public function getConnector(): DataConnector {
        return $this->connector;
    }

    public function init() {
        $this->connector->executeGeneric("replay.init");
    }

    public function save(ReplayInformation $information, string $compressedData, \Closure $onSuccess, ?\Closure $onError = null) {
        $id = $information->getIdentifier();
        $this->connector->executeChange(
            "replay.save",
            [
                "id"           => $id,
                "timestamp"    => $information->getTimestamp()->getTimestamp(),
                "duration"     => $information->getDuration(),
                "game_details" => $information->getGameDetails(),
                "data"         => $compressedData,
            ],
            function(int $affectedRows) use ($id, $onSuccess): void {
                $onSuccess($id);
            },
            function(SqlError $error) use ($onError): void {
                $onError !== null ? $onError($error) : throw $error;
            }
        );
    }

    public function load(string $replayId, \Closure $onSuccess, ?\Closure $onError = null) {
        $this->connector->executeSelect(
            "replay.load",
            [
                "id" => $replayId,
            ],
            function(array $rows) use ($replayId, $onSuccess, $onError): void {
                if (empty($rows)) {
                    $err = new RuntimeException("Replay not found in database: {$replayId}");
                    $onError !== null ? $onError($err) : throw $err;
                    return;
                }

                $row = $rows[0];
                $info = new ReplayInformation(
                    (string)$row["id"],
                    (new DateTime())->setTimestamp((int)$row["timestamp"]),
                    (int)$row["duration"],
                    (string)$row["game_details"]
                );

                $compressed = (string)$row["data"];
                ReplayAPI::decompressInBackground(
                    $compressed,
                    function(string $rawBuffer) use ($info, $onSuccess): void {
                        $onSuccess($info, $rawBuffer);
                    },
                    $onError
                );
            },
            function(SqlError $error) use ($onError): void {
                $onError !== null ? $onError($error) : throw $error;
            }
        );
    }

    public function delete(string $replayId, ?\Closure $onSuccess = null, ?\Closure $onError = null) {
        $this->connector->executeChange(
            "replay.delete",
            [
                "id" => $replayId,
            ],
            function(int $affectedRows) use ($onSuccess): void {
                if ($onSuccess !== null) {
                    $onSuccess();
                }
            },
            function(SqlError $error) use ($onError): void {
                $onError !== null ? $onError($error) : throw $error;
            }
        );
    }

    public function fetch(string $replayId, \Closure $onSuccess, ?\Closure $onError = null) {
        $this->connector->executeSelect(
            "replay.fetch",
            [
                "id" => $replayId,
            ],
            function(array $rows) use ($replayId, $onSuccess, $onError): void {
                if (empty($rows)) {
                    $err = new RuntimeException("Replay not found in database: {$replayId}");
                    $onError !== null ? $onError($err) : throw $err;
                    return;
                }

                $row = $rows[0];
                $info = new ReplayInformation(
                    (string)$row["id"],
                    (new DateTime())->setTimestamp((int)$row["timestamp"]),
                    (int)$row["duration"],
                    (string)$row["game_details"]
                );

                $onSuccess($info);
            },
            function(SqlError $error) use ($onError): void {
                $onError !== null ? $onError($error) : throw $error;
            }
        );
    }

    public function fetchAll(\Closure $onSuccess, ?\Closure $onError = null) {
        $this->connector->executeSelect(
            "replay.fetch_all",
            [],
            function(array $rows) use ($onSuccess): void {
                $replays = [];
                foreach ($rows as $row) {
                    $replays[] = new ReplayInformation(
                        (string)$row["id"],
                        (new DateTime())->setTimestamp((int)$row["timestamp"]),
                        (int)$row["duration"],
                        (string)$row["game_details"]
                    );
                }
                $onSuccess($replays);
            },
            function(SqlError $error) use ($onError): void {
                $onError !== null ? $onError($error) : throw $error;
            }
        );
    }

    public function close(): void {
        $this->connector->close();
    }
}
