<?php

declare(strict_types=1);

namespace bitpvp\replay\protocol;

use pocketmine\nbt\BaseNbtSerializer;
use pocketmine\nbt\NbtDataException;
use function count;
use function strlen;

/**
 * NBT codec for the replay format. It preserves the existing Bedrock network NBT byte layout
 * without depending on protocol-specific NBT helpers.
 */
final class ReplayNbtSerializer extends BaseNbtSerializer {

    public function readShort(): int {
        return $this->buffer->getLShort();
    }

    public function readSignedShort(): int {
        return $this->buffer->getSignedLShort();
    }

    public function writeShort(int $v): void {
        $this->buffer->putLShort($v);
    }

    public function readInt(): int {
        return $this->buffer->getVarInt();
    }

    public function writeInt(int $v): void {
        $this->buffer->putVarInt($v);
    }

    public function readLong(): int {
        return $this->buffer->getVarLong();
    }

    public function writeLong(int $v): void {
        $this->buffer->putVarLong($v);
    }

    public function readString(): string {
        return $this->buffer->get(self::checkReadStringLength($this->buffer->getUnsignedVarInt()));
    }

    public function writeString(string $v): void {
        $this->buffer->putUnsignedVarInt(self::checkWriteStringLength(strlen($v)));
        $this->buffer->put($v);
    }

    public function readFloat(): float {
        return $this->buffer->getLFloat();
    }

    public function writeFloat(float $v): void {
        $this->buffer->putLFloat($v);
    }

    public function readDouble(): float {
        return $this->buffer->getLDouble();
    }

    public function writeDouble(float $v): void {
        $this->buffer->putLDouble($v);
    }

    public function readIntArray(): array {
        $length = $this->readInt();
        if ($length < 0) {
            throw new NbtDataException("Array length cannot be less than zero ($length < 0)");
        }
        $result = [];
        for ($i = 0; $i < $length; ++$i) {
            $result[] = $this->readInt();
        }
        return $result;
    }

    public function writeIntArray(array $array): void {
        $this->writeInt(count($array));
        foreach ($array as $value) {
            $this->writeInt($value);
        }
    }
}
