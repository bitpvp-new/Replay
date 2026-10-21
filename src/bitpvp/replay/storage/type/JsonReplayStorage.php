<?php

declare(strict_types=1);

namespace bitpvp\replay\storage\type;

use bitpvp\replay\data\ReplayInformation;
use bitpvp\replay\ReplayAPI;
use bitpvp\replay\storage\ReplayStorage;
use bitpvp\replay\thread\packet\DeleteStorageFilePacket;
use bitpvp\replay\thread\packet\FetchAllJsonStoragePacket;
use bitpvp\replay\thread\packet\LoadJsonStoragePacket;
use bitpvp\replay\thread\packet\SaveJsonStoragePacket;
use RuntimeException;

final class JsonReplayStorage implements ReplayStorage {

    private string $directory;

    public function __construct(string $directory) {
        $this->directory = rtrim($directory, '/\\');
    }

    public function getDirectory(): string {
        return $this->directory;
    }

    public function getJsonPath(string $replayId): string {
        return $this->directory . DIRECTORY_SEPARATOR . $replayId . ".json";
    }

    public function getReplayPath(string $replayId): string {
        return $this->directory . DIRECTORY_SEPARATOR . $replayId . ".replay";
    }

    public function save(ReplayInformation $information, string $compressedData, \Closure $onSuccess, ?\Closure $onError = null): void {
        $id = $information->getIdentifier();
        $json = json_encode($information->serialize(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $err = new RuntimeException("Failed to encode ReplayInformation to JSON for {$id}");
            $onError !== null ? $onError($err) : throw $err;
            return;
        }

        ReplayAPI::registerStorageSaveCallback($id, $onSuccess, $onError);
        ReplayAPI::getThread()->submit(new SaveJsonStoragePacket(
            $id,
            $this->getJsonPath($id),
            $json,
            $this->getReplayPath($id),
            $compressedData,
            true
        ));
    }

    public function load(string $replayId, \Closure $onSuccess, ?\Closure $onError = null): void {
        ReplayAPI::registerStorageLoadCallback($replayId, function(string $jsonRaw, string $rawBuffer) use ($onSuccess, $onError): void {
            $data = json_decode($jsonRaw, true);
            if (!is_array($data)) {
                $err = new RuntimeException("Corrupted JSON metadata");
                if ($onError !== null) {
                    $onError($err);
                }
                return;
            }
            $info = ReplayInformation::deserialize($data);
            if ($info === null) {
                $err = new RuntimeException("Invalid ReplayInformation data in JSON");
                if ($onError !== null) {
                    $onError($err);
                }
                return;
            }
            $onSuccess($info, $rawBuffer);
        }, $onError);

        ReplayAPI::getThread()->submit(new LoadJsonStoragePacket(
            $replayId,
            $this->getJsonPath($replayId),
            $this->getReplayPath($replayId)
        ));
    }

    public function delete(string $replayId, ?\Closure $onSuccess = null, ?\Closure $onError = null): void {
        $token = uniqid("del_");
        $paths = [$this->getJsonPath($replayId), $this->getReplayPath($replayId)];

        ReplayAPI::registerStorageDeleteCallback($token, $onSuccess ?? static function (): void {}, $onError);
        ReplayAPI::getThread()->submit(new DeleteStorageFilePacket(
            $token,
            json_encode($paths, JSON_THROW_ON_ERROR)
        ));
    }

    public function fetch(string $replayId, \Closure $onSuccess, ?\Closure $onError = null): void {
        $this->load($replayId, $onSuccess(...), $onError);
    }

    public function fetchAll(\Closure $onSuccess, ?\Closure $onError = null): void {
        $token = uniqid("fetch_all_");
        ReplayAPI::registerStorageFetchAllCallback($token, function(string $itemsJson) use ($onSuccess): void {
            $rawItems = json_decode($itemsJson, true);
            $replays = [];
            if (is_array($rawItems)) {
                foreach ($rawItems as $item) {
                    if (is_array($item)) {
                        $info = ReplayInformation::deserialize($item);
                        if ($info !== null) {
                            $replays[] = $info;
                        }
                    }
                }
            }
            $onSuccess($replays);
        }, $onError);

        ReplayAPI::getThread()->submit(new FetchAllJsonStoragePacket(
            $token,
            $this->directory
        ));
    }

    public function close(): void {}
}
