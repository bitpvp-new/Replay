<?php

namespace bitpvp\replay\storage\type;

use bitpvp\replay\data\ReplayInformation;
use bitpvp\replay\storage\ReplayStorage;
use Closure;
use Generator;
use pocketmine\plugin\Plugin;
use SOFe\AwaitGenerator\Await;

final class AwaitMySQLReplayStorage implements ReplayStorage {

    public function __construct(
        readonly private MySQLReplayStorage $inner
    ){

    }

    /**
     * @return MySQLReplayStorage
     */
    public function getInner(): MySQLReplayStorage {
        return $this->inner;
    }

    public static function create(Plugin $plugin, array $settings, string $sqlFile = "mysql.sql"): self {
        return new self(MySQLReplayStorage::create($plugin, $settings, $sqlFile));
    }

    public function fetch(string $replayId, Closure $onSuccess, ?Closure $onError = null): Generator {
        return yield from Await::promise(function () use ($replayId, $onSuccess, $onError) {
            $this->inner->fetch($replayId, $onSuccess, $onError);
        });
    }

    public function load(string $replayId, Closure $onSuccess, ?Closure $onError = null): Generator {
        return yield from Await::promise(function () use ($replayId, $onSuccess, $onError) {
            $this->inner->load($replayId, $onSuccess, $onError);
        });
    }

    public function save(ReplayInformation $information, string $compressedData, Closure $onSuccess, ?Closure $onError = null): Generator{
        return yield from Await::promise(function () use ($information, $compressedData, $onSuccess, $onError) {
            $this->inner->save($information, $compressedData, $onSuccess, $onError);
        });
    }

    public function fetchAll(Closure $onSuccess, ?Closure $onError = null): Generator {
        return yield from Await::promise(function () use ($onSuccess, $onError) {
            $this->inner->fetchAll($onSuccess, $onError);
        });
    }

    public function delete(string $replayId, ?Closure $onSuccess = null, ?Closure $onError = null): Generator {
        return yield from Await::promise(function () use ($replayId, $onSuccess, $onError) {
            $this->inner->delete($replayId, $onSuccess, $onError);
        });
    }

    public function close(): void {
        $this->inner->close();
    }
}