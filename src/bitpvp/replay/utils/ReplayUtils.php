<?php

declare(strict_types=1);

namespace bitpvp\replay\utils;

use Closure;
use pocketmine\event\Event;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Utils;
use pocketmine\world\World;

final class ReplayUtils
{
    private function __construct() {}



    /** @return array{int, int} [chunkX, chunkZ] */
    public static function xzFromHash(int $hash): array
    {
        World::getXZ($hash, $x, $z);
        return [$x, $z];
    }

    /**
     * @param Closure $executor
     * @return string
     */
    public static function parseEventHandler(Closure $executor): string
    {
        Utils::validateCallableSignature(fn(Event $_): bool => false, $executor);

        return (function () use ($executor): string {
            try {
                $reflection = new \ReflectionFunction($executor);
            } catch (\ReflectionException $e) {
                throw new AssumptionFailedError();
            }
            if (empty($reflection->getParameters())) {
                throw new AssumptionFailedError("Should have been validated early");
            }

            $parameter = $reflection->getParameters()[0]->getType()->getName();
            // TODO: should we check for class instance?
            return $parameter;
        })();
    }
}
