<?php

declare(strict_types=1);

namespace bitpvp\replay\utils;

use bitpvp\replay\protocol\ReplayProtocol;
use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\block\Block;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\entity\Location;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\entity\MetadataProperty;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;

final class BinaryStream {

    private ByteBufferWriter $writer;
    private ByteBufferReader $reader;
    private LittleEndianNbtSerializer $serializer;

    public function __construct(?string $buffer = null, int $offset = 0) {
        $this->reader = new ByteBufferReader($buffer ?? "");
        $this->reader->setOffset($offset);
        $this->writer = new ByteBufferWriter();
        $this->serializer = new LittleEndianNbtSerializer();
    }

    public static function encoder(): self {
        return new self();
    }

    public static function decoder(string $buffer, int $offset = 0): self {
        return new self($buffer, $offset);
    }

    public function getBuffer(): string {
        return $this->writer->getData();
    }

    public function getOffset(): int {
        return $this->reader->getOffset();
    }

    public function setOffset(int $offset): void {
        $this->reader->setOffset($offset);
    }

    public function getUnreadLength(): int {
        return $this->reader->getUnreadLength();
    }

    public function putByte(int $v): void {
        Byte::writeUnsigned($this->writer, $v);
    }

    public function getByte(): int {
        return Byte::readUnsigned($this->reader);
    }

    public function putBool(bool $v): void {
        Byte::writeUnsigned($this->writer, $v ? 1 : 0);
    }

    public function getBool(): bool {
        return Byte::readUnsigned($this->reader) !== 0;
    }

    public function putShort(int $v): void {
        LE::writeSignedShort($this->writer, $v);
    }

    public function getShort(): int {
        return LE::readSignedShort($this->reader);
    }

    public function putInt(int $v): void {
        LE::writeSignedInt($this->writer, $v);
    }

    public function getInt(): int {
        return LE::readSignedInt($this->reader);
    }

    public function putLong(int $v): void {
        LE::writeSignedLong($this->writer, $v);
    }

    public function getLong(): int {
        return LE::readSignedLong($this->reader);
    }

    public function putVarInt(int $v): void {
        VarInt::writeUnsignedInt($this->writer, $v);
    }

    public function getVarInt(): int {
        return VarInt::readUnsignedInt($this->reader);
    }

    public function putFloat(float $v): void {
        LE::writeFloat($this->writer, $v);
    }

    public function getFloat(): float {
        return LE::readFloat($this->reader);
    }

    public function putDouble(float $v): void {
        LE::writeDouble($this->writer, $v);
    }

    public function getDouble(): float {
        return LE::readDouble($this->reader);
    }

    public function putString(string $v): void {
        VarInt::writeUnsignedInt($this->writer, strlen($v));
        $this->writer->writeByteArray($v);
    }

    public function getString(): string {
        $len = VarInt::readUnsignedInt($this->reader);
        return $this->reader->readByteArray($len);
    }

    public function put(string $bytes): void {
        $this->writer->writeByteArray($bytes);
    }

    public function get(int $len): string {
        return $this->reader->readByteArray($len);
    }

    public function putVector3(Vector3 $vec): void {
        $this->putFloat((float)$vec->x);
        $this->putFloat((float)$vec->y);
        $this->putFloat((float)$vec->z);
    }

    public function getVector3(): Vector3 {
        return new Vector3($this->getFloat(), $this->getFloat(), $this->getFloat());
    }

    public function putVector3Nullable(?Vector3 $vec): void {
        $this->putBool($vec !== null);
        if ($vec !== null) {
            $this->putVector3($vec);
        }
    }

    public function getVector3Nullable(): ?Vector3 {
        return $this->getBool() ? $this->getVector3() : null;
    }

    public function putLocation(Location $loc): void {
        $this->putVector3($loc->asVector3());
        $this->putFloat($loc->yaw);
        $this->putFloat($loc->pitch);
    }

    public function getLocation(): Location {
        $v = $this->getVector3();
        $yaw = $this->getFloat();
        $pitch = $this->getFloat();
        return new Location($v->x, $v->y, $v->z, null, $yaw, $pitch);
    }

    public function putBlockPosition(BlockPosition $pos): void {
        $this->putInt($pos->getX());
        $this->putInt($pos->getY());
        $this->putInt($pos->getZ());
    }

    public function getBlockPosition(): BlockPosition {
        return new BlockPosition($this->getInt(), $this->getInt(), $this->getInt());
    }

    public function putNbtCompound(CompoundTag $tag): void {
        $this->putString($this->serializer->write(new TreeRoot($tag)));
    }

    public function getNbtCompound(): CompoundTag {
        return $this->serializer->read($this->getString())->mustGetCompoundTag();
    }

    public function putSkin(SkinData $skin): void {
        ReplayProtocol::writeSkin($this->writer, $skin);
    }

    public function getSkin(): SkinData {
        return ReplayProtocol::readSkin($this->reader);
    }

    /**
     * @param array<int, MetadataProperty> $metadata
     */
    public function putEntityMetadata(array $metadata): void {
        ReplayProtocol::writeEntityMetadata($this->writer, $metadata);
    }

    /**
     * @return array<int, MetadataProperty>
     */
    public function getEntityMetadata(): array {
        return ReplayProtocol::readEntityMetadata($this->reader);
    }

    public function putBlock(Block $block): void {
        $this->putString($this->serializer->write(new TreeRoot(GlobalBlockStateHandlers::getSerializer()->serializeBlock($block)->toNbt())));
    }

    public function getBlock(): Block {
        return GlobalBlockStateHandlers::getDeserializer()->deserializeBlock(BlockStateData::fromNbt($this->serializer->read($this->getString())->mustGetCompoundTag()));
    }

    public function putItem(Item $item): void {
        $null = $item->isNull();
        $this->putBool($null);
        if ($null) {
            return;
        }
        $serialized = GlobalItemDataHandlers::getSerializer()->serializeType($item);
        $this->putString($serialized->getName());
        $this->putInt($serialized->getMeta());
        $state = $serialized->getBlock();
        $this->putBool($state !== null);
        if ($state !== null) {
            $this->putString($this->serializer->write(new TreeRoot($state->toNbt())));
        }
        $this->putBool($item->hasEnchantments());
    }

    public function getItem(): Item {
        if ($this->getBool()) {
            return VanillaItems::AIR();
        }
        $name = $this->getString();
        $meta = $this->getInt();
        $hasBlock = $this->getBool();
        $blockData = $hasBlock ? BlockStateData::fromNbt($this->serializer->read($this->getString())->mustGetCompoundTag()) : null;

        $item = GlobalItemDataHandlers::getDeserializer()->deserializeType(new SavedItemData($name, $meta, $blockData));
        if ($this->getBool()) {
            $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::INFINITY()));
        }
        return $item;
    }

    /**
     * @param array<int, Item> $contents
     */
    public function putContents(array $contents): void {
        $valid = array_filter($contents, fn(Item $item) => !$item->isNull());
        $this->putVarInt(count($valid));
        foreach ($valid as $slot => $item) {
            $this->putInt((int)$slot);
            $this->putItem($item);
        }
    }

    /**
     * @return array<int, Item>
     */
    public function getContents(): array {
        $contents = [];
        $count = $this->getVarInt();
        for ($i = 0; $i < $count; $i++) {
            $slot = $this->getInt();
            $contents[$slot] = $this->getItem();
        }
        return $contents;
    }
}
