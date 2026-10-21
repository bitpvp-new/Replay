<?php

declare(strict_types=1);

namespace bitpvp\replay\data;

use DateTime;

class ReplayInformation {

    public function __construct(
        private string $identifier,
        private DateTime $timestamp,
        private int $duration,
        private string $gameDetails = "",
    ) {}

    public static function generateIdentifier(int $length = 8): string {
        return substr(bin2hex(random_bytes(16)), 0, $length);
    }

    public static function create(string $identifier, DateTime $timestamp, int $duration, string $details = ""): self {
        return new self($identifier, $timestamp, $duration, $details);
    }

    public function getIdentifier(): string {
        return $this->identifier;
    }

    public function getTimestamp(): DateTime {
        return $this->timestamp;
    }

    public function getDuration(): int {
        return $this->duration;
    }

    public function getGameDetails(): string {
        return $this->gameDetails;
    }

    public function serialize(): array {
        return [
            "identifier"  => $this->identifier,
            "timestamp"   => $this->timestamp->format("Y-m-d H:i:s"),
            "duration"    => $this->duration,
            "gameDetails" => $this->gameDetails,
        ];
    }

    public static function deserialize(array $data): ?static {
        if (!isset($data['identifier'], $data['timestamp'], $data['duration'])) {
            return null;
        }
        return new static(
            (string)$data['identifier'],
            new DateTime((string)$data['timestamp']),
            (int)$data['duration'],
            (string)($data['gameDetails'] ?? "")
        );
    }
}
