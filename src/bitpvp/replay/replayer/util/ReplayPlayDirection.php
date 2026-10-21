<?php

declare(strict_types=1);

namespace bitpvp\replay\replayer\util;

enum ReplayPlayDirection: string {
    case FORWARDS  = "forwards";
    case BACKWARDS = "backwards";

    public function isForwards(): bool {
        return $this === self::FORWARDS;
    }

    public function isBackwards(): bool {
        return $this === self::BACKWARDS;
    }

    public function opposite(): self {
        return $this === self::FORWARDS ? self::BACKWARDS : self::FORWARDS;
    }

    public static function FORWARDS(): self {
        return self::FORWARDS;
    }

    public static function BACKWARDS(): self {
        return self::BACKWARDS;
    }

    public function equals(self $other): bool {
        return $this === $other;
    }
}
