<?php

declare(strict_types=1);

namespace bitpvp\replay\utils;

use pocketmine\timings\TimingsHandler;

final class ReplayTimings {

    private static ?TimingsHandler $recordingTick = null;
    private static ?TimingsHandler $replayTick = null;
    private static ?TimingsHandler $dataEncode = null;
    private static ?TimingsHandler $dataDecode = null;

    private function __construct() {}

    public static function recordingTick(): TimingsHandler {
        return self::$recordingTick ??= new TimingsHandler("Replay - Recording Tick", group: "Replay");
    }

    public static function replayTick(): TimingsHandler {
        return self::$replayTick ??= new TimingsHandler("Replay - Playback Tick", group: "Replay");
    }

    public static function dataEncode(): TimingsHandler {
        return self::$dataEncode ??= new TimingsHandler("Replay - Data Encode", group: "Replay");
    }

    public static function dataDecode(): TimingsHandler {
        return self::$dataDecode ??= new TimingsHandler("Replay - Data Decode", group: "Replay");
    }
}
