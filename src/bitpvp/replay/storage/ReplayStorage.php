<?php

declare(strict_types=1);

namespace bitpvp\replay\storage;

use bitpvp\replay\data\ReplayInformation;

interface ReplayStorage {

    /**
     * @param \Closure(string $replayId): void $onSuccess
     * @param (\Closure(\Throwable $error): void)|null $onError
     */
    public function save(ReplayInformation $information, string $compressedData, \Closure $onSuccess, ?\Closure $onError = null);

    /**
     * @param \Closure(ReplayInformation $information, string $compressedData): void $onSuccess
     * @param (\Closure(\Throwable $error): void)|null $onError
     */
    public function load(string $replayId, \Closure $onSuccess, ?\Closure $onError = null);

    /**
     * @param (\Closure(): void)|null $onSuccess
     * @param (\Closure(\Throwable $error): void)|null $onError
     */
    public function delete(string $replayId, ?\Closure $onSuccess = null, ?\Closure $onError = null);

    /**
     * @param \Closure(ReplayInformation $information): void $onSuccess
     * @param (\Closure(\Throwable $error): void)|null $onError
     */
    public function fetch(string $replayId, \Closure $onSuccess, ?\Closure $onError = null);

    /**
     * @param \Closure(ReplayInformation[] $replays): void $onSuccess
     * @param (\Closure(\Throwable $error): void)|null $onError
     */
    public function fetchAll(\Closure $onSuccess, ?\Closure $onError = null);

    public function close(): void;
}
