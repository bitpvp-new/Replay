<?php

declare(strict_types=1);

namespace bitpvp\replay\data;

use bitpvp\replay\utils\BinaryStream;
use bitpvp\replay\utils\IlegalHandlingException;
use Generator;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;

class WorldData {

    /** @var array<int, Chunk> */
    private array $chunks = [];

    /** @var CompoundTag[] */
    private array $tiles = [];
    private Vector3 $spawn;
    private int $initialTime;

    public static function create(World $world): self {
        $worldData = new self();
        $worldData->spawn = $world->getSafeSpawn();
        $worldData->initialTime = $world->getTimeOfDay();
        return $worldData;
    }

    public static function fromData(array $chunks, array $tiles, Vector3 $spawn, int $initialTime): self {
        $worldData = new self();
        $worldData->chunks = $chunks;
        $worldData->tiles = $tiles;
        $worldData->spawn = $spawn;
        $worldData->initialTime = $initialTime;
        return $worldData;
    }

    public function addChunk(Chunk $chunk, int $chunkHash): void {
        if(isset($this->chunks[$chunkHash])) {
            throw IlegalHandlingException::create("Cannot override chunk #$chunkHash fromWorldData");
        }
        $this->chunks[$chunkHash] = clone $chunk;
    }

    public function removeChunk(Chunk $chunk, int $chunkHash): void {
        if(!isset($this->chunks[$chunkHash])) {
            throw IlegalHandlingException::create("Cannot remove chunk #$chunkHash from WorldData, chunk does not exist");
        }
        unset($this->chunks[$chunkHash]);
    }

    /**
     * @return array<int, Chunk>
     */
    public function getChunks(): array {
        return $this->chunks;
    }

    /**
     * @return CompoundTag[]
     */
    public function getTiles(): array {
        return $this->tiles;
    }

    public function getSpawn(): Vector3 {
        return $this->spawn;
    }

    public function getInitialTime(): int {
        return $this->initialTime;
    }

    /**
     * @param BinaryStream $stream
     * @return void
     * @deprecated
     */
    public function encode(BinaryStream $stream): void {
        foreach ($this->encodeSteps($stream) as $_) {

        }
    }

    /** @return Generator<mixed, null, mixed, void> */
    public function encodeSteps(BinaryStream $stream): Generator {
        $stream->putInt(count($this->chunks));
        foreach ($this->chunks as $hash => $chunk) {
            $stream->putLong($hash);
            $serialized = FastChunkSerializer::serializeTerrain($chunk);
            $stream->putString(zlib_encode($serialized, ZLIB_ENCODING_RAW, 9));

            $tiles = $chunk->getTiles();
            $stream->putInt(count($tiles));
            foreach ($tiles as $tile) {
                $stream->putNbtCompound($tile->saveNBT());
                yield null;
            }
            yield null;
        }
        $stream->putVector3($this->spawn);
        $stream->putInt($this->initialTime);
    }

    /**
     * @param BinaryStream $stream
     * @return self
     * @deprecated
     */
    public static function decode(BinaryStream $stream): self {
        $steps = self::decodeSteps($stream);
        foreach ($steps as $_) {

        }
        return $steps->getReturn();
    }

    /** @return Generator<mixed, null, mixed, self> */
    public static function decodeSteps(BinaryStream $stream): Generator {
        $chunks = [];
        $tiles = [];
        $chunkCount = $stream->getInt();
        for ($i = 0; $i < $chunkCount; $i++) {
            $hash = $stream->getLong();
            $rawTerrain = zlib_decode($stream->getString());
            $chunks[$hash] = FastChunkSerializer::deserializeTerrain($rawTerrain);

            $tileCount = $stream->getInt();
            yield null;
            for ($j = 0; $j < $tileCount; $j++) {
                $tiles[] = $stream->getNbtCompound();
                yield null;
            }
        }
        $spawn = $stream->getVector3();
        $initialTime = $stream->getInt();

        return self::fromData($chunks, $tiles, $spawn, $initialTime);
    }
}
