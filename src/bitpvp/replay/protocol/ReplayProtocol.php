<?php

declare(strict_types=1);

namespace bitpvp\replay\protocol;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\color\Color;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\entity\BlockPosMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\ByteMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\CompoundTagMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\FloatMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\MetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\ShortMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\Vec3MetadataProperty;
use pocketmine\network\mcpe\protocol\types\skin\PersonaPieceTintColor;
use pocketmine\network\mcpe\protocol\types\skin\PersonaSkinPiece;
use pocketmine\network\mcpe\protocol\types\skin\SkinAnimation;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use ReflectionMethod;
use ReflectionNamedType;
use UnexpectedValueException;

/**
 * Owns the replay's binary representation of Bedrock-facing values instead of delegating to packet serializers.
 */
final class ReplayProtocol {

    private const int METADATA_BYTE = 0;
    private const int METADATA_SHORT = 1;
    private const int METADATA_INT = 2;
    private const int METADATA_FLOAT = 3;
    private const int METADATA_STRING = 4;
    private const int METADATA_COMPOUND_TAG = 5;
    private const int METADATA_BLOCK_POSITION = 6;
    private const int METADATA_LONG = 7;
    private const int METADATA_VECTOR3 = 8;

    private function __construct() {}

    public static function writeSkin(ByteBufferWriter $writer, SkinData $skin): void {
        self::writeString($writer, $skin->getSkinId());
        self::writeString($writer, $skin->getPlayFabId());
        self::writeString($writer, $skin->getResourcePatch());
        self::writeSkinImage($writer, $skin->getSkinImage());

        $animations = $skin->getAnimations();
        LE::writeUnsignedInt($writer, count($animations));
        foreach ($animations as $animation) {
            self::writeSkinImage($writer, $animation->getImage());
            LE::writeUnsignedInt($writer, $animation->getType());
            LE::writeFloat($writer, $animation->getFrames());
            LE::writeUnsignedInt($writer, $animation->getExpressionType());
        }

        self::writeSkinImage($writer, $skin->getCapeImage());
        self::writeString($writer, self::getSkinGeometryData($skin));
        self::writeString($writer, $skin->getGeometryDataEngineVersion());
        self::writeString($writer, $skin->getAnimationData());
        self::writeString($writer, $skin->getCapeId());
        self::writeString($writer, $skin->getFullSkinId());
        self::writeString($writer, self::getSkinArmSize($skin));
        self::writeString($writer, self::getSkinColor($skin));

        $personaPieces = $skin->getPersonaPieces();
        LE::writeUnsignedInt($writer, count($personaPieces));
        foreach ($personaPieces as $piece) {
            self::writeString($writer, $piece->getPieceId());
            self::writeString($writer, $piece->getPieceType());
            self::writeString($writer, $piece->getPackId());
            self::writeBool($writer, $piece->isDefaultPiece());
            self::writeString($writer, $piece->getProductId());
        }

        $pieceTintColors = $skin->getPieceTintColors();
        LE::writeUnsignedInt($writer, count($pieceTintColors));
        foreach ($pieceTintColors as $tint) {
            self::writeString($writer, $tint->getPieceType());
            $colors = $tint->getColors();
            LE::writeUnsignedInt($writer, count($colors));
            foreach ($colors as $color) {
                self::writeString($writer, $color);
            }
        }

        self::writeBool($writer, $skin->isPremium());
        self::writeBool($writer, $skin->isPersona());
        self::writeBool($writer, $skin->isPersonaCapeOnClassic());
        self::writeBool($writer, $skin->isPrimaryUser());
        self::writeBool($writer, $skin->isOverride());
    }

    public static function readSkin(ByteBufferReader $reader): SkinData {
        $skinId = self::readString($reader);
        $playFabId = self::readString($reader);
        $resourcePatch = self::readString($reader);
        $skinImage = self::readSkinImage($reader);

        $animationCount = LE::readUnsignedInt($reader);
        $animations = [];
        for ($i = 0; $i < $animationCount; ++$i) {
            $animations[] = new SkinAnimation(
                self::readSkinImage($reader),
                LE::readUnsignedInt($reader),
                LE::readFloat($reader),
                LE::readUnsignedInt($reader)
            );
        }

        $capeImage = self::readSkinImage($reader);
        $geometryData = self::readString($reader);
        $geometryDataEngineVersion = self::readString($reader);
        $animationData = self::readString($reader);
        $capeId = self::readString($reader);
        $fullSkinId = self::readString($reader);
        $armSize = self::readString($reader);
        $skinColor = self::readString($reader);

        $personaPieceCount = LE::readUnsignedInt($reader);
        $personaPieces = [];
        for ($i = 0; $i < $personaPieceCount; ++$i) {
            $personaPieces[] = new PersonaSkinPiece(
                self::readString($reader),
                self::readString($reader),
                self::readString($reader),
                self::readBool($reader),
                self::readString($reader)
            );
        }

        $pieceTintColorCount = LE::readUnsignedInt($reader);
        $pieceTintColors = [];
        for ($i = 0; $i < $pieceTintColorCount; ++$i) {
            $pieceType = self::readString($reader);
            $colorCount = LE::readUnsignedInt($reader);
            $colors = [];
            for ($j = 0; $j < $colorCount; ++$j) {
                $colors[] = self::readString($reader);
            }
            $pieceTintColors[] = new PersonaPieceTintColor($pieceType, $colors);
        }

        $skinDataArguments = [
            $skinId,
            $playFabId,
            $resourcePatch,
            $skinImage,
            $animations,
            $capeImage,
            $geometryData,
            $geometryDataEngineVersion,
            $animationData,
            $capeId,
            $fullSkinId,
            $armSize,
            $skinColor,
            $personaPieces,
            $pieceTintColors
        ];

        if (method_exists(SkinData::class, "getGeometryDataJson")) {
            $skinDataArguments[11] = self::convertSkinArmSize($armSize);
            $skinDataArguments[12] = self::convertSkinColor($skinColor);
            // Axolotl-PM replaced the old isVerified boolean with a trusted-skin flag string.
            $skinDataArguments[] = defined(SkinData::class . "::TRUSTED_SKIN_TRUE") ? SkinData::TRUSTED_SKIN_TRUE : "true";
        } else {
            // The old SkinData constructor has an isVerified parameter here.
            $skinDataArguments[] = true;
        }

        array_push(
            $skinDataArguments,
            self::readBool($reader),
            self::readBool($reader),
            self::readBool($reader),
            self::readBool($reader),
            self::readBool($reader)
        );

        return new SkinData(...$skinDataArguments);
    }

    private static function getSkinGeometryData(SkinData $skin): string {
        return method_exists($skin, "getGeometryDataJson") ? $skin->getGeometryDataJson() : $skin->getGeometryData();
    }

    private static function getSkinArmSize(SkinData $skin): string {
        $armSize = $skin->getArmSize();
        if (is_string($armSize)) {
            return $armSize;
        }
        if (property_exists($armSize, "value") && is_string($armSize->value)) {
            return $armSize->value;
        }
        return strtolower($armSize->name);
    }

    private static function getSkinColor(SkinData $skin): string {
        $color = $skin->getSkinColor();
        if (is_string($color)) {
            return $color;
        }
        return sprintf("@argb:%08x", $color->toARGB());
    }

    private static function convertSkinArmSize(string $armSize): mixed {
        $constructor = new ReflectionMethod(SkinData::class, "__construct");
        $type = $constructor->getParameters()[11]->getType();
        if (!$type instanceof ReflectionNamedType || !enum_exists($type->getName())) {
            return $armSize;
        }

        $enum = new \ReflectionEnum($type->getName());
        foreach ($enum->getCases() as $case) {
            if (strtolower($case->getName()) === strtolower($armSize)) {
                return $case->getValue();
            }
            if ($enum->isBacked() && $case->getValue()->value === $armSize) {
                return $case->getValue();
            }
        }

        throw new UnexpectedValueException("Unsupported skin arm size '$armSize'");
    }

    private static function convertSkinColor(string $skinColor): mixed {
        $constructor = new ReflectionMethod(SkinData::class, "__construct");
        $type = $constructor->getParameters()[12]->getType();
        if (!$type instanceof ReflectionNamedType || !class_exists($type->getName())) {
            return $skinColor;
        }

        $colorClass = $type->getName();
        assert($colorClass instanceof Color);
        if (preg_match('/^@argb:([0-9a-fA-F]{8})$/', $skinColor, $matches) === 1) {
            return $colorClass::fromARGB((int)hexdec($matches[1]));
        }

        if (preg_match('/^#?([0-9a-fA-F]{6})$/', $skinColor, $matches) === 1) {
            return $colorClass::fromRGB((int)hexdec($matches[1]));
        }

        return new $colorClass(0, 0, 0, 0);
    }

    /** @param array<int, MetadataProperty> $metadata */
    public static function writeEntityMetadata(ByteBufferWriter $writer, array $metadata): void {
        VarInt::writeUnsignedInt($writer, count($metadata));
        foreach ($metadata as $key => $property) {
            VarInt::writeUnsignedInt($writer, $key);
            VarInt::writeUnsignedInt($writer, self::getMetadataType($property));
            self::writeMetadataValue($writer, $property);
        }
    }

    /** @return array<int, MetadataProperty> */
    public static function readEntityMetadata(ByteBufferReader $reader): array {
        $count = VarInt::readUnsignedInt($reader);
        $metadata = [];
        for ($i = 0; $i < $count; ++$i) {
            $key = VarInt::readUnsignedInt($reader);
            $type = VarInt::readUnsignedInt($reader);
            $metadata[$key] = self::readMetadataValue($reader, $type);
        }
        return $metadata;
    }

    private static function getMetadataType(MetadataProperty $property): int {
        return match (true) {
            $property instanceof ByteMetadataProperty => self::METADATA_BYTE,
            $property instanceof ShortMetadataProperty => self::METADATA_SHORT,
            $property instanceof IntMetadataProperty => self::METADATA_INT,
            $property instanceof FloatMetadataProperty => self::METADATA_FLOAT,
            $property instanceof StringMetadataProperty => self::METADATA_STRING,
            $property instanceof CompoundTagMetadataProperty => self::METADATA_COMPOUND_TAG,
            $property instanceof BlockPosMetadataProperty => self::METADATA_BLOCK_POSITION,
            $property instanceof LongMetadataProperty => self::METADATA_LONG,
            $property instanceof Vec3MetadataProperty => self::METADATA_VECTOR3,
            default => throw new UnexpectedValueException("Unsupported replay metadata property " . $property::class)
        };
    }

    private static function writeMetadataValue(ByteBufferWriter $writer, MetadataProperty $property): void {
        if ($property instanceof ByteMetadataProperty) {
            Byte::writeSigned($writer, $property->getValue());
        } elseif ($property instanceof ShortMetadataProperty) {
            LE::writeSignedShort($writer, $property->getValue());
        } elseif ($property instanceof IntMetadataProperty) {
            VarInt::writeSignedInt($writer, $property->getValue());
        } elseif ($property instanceof FloatMetadataProperty) {
            LE::writeFloat($writer, $property->getValue());
        } elseif ($property instanceof StringMetadataProperty) {
            self::writeString($writer, $property->getValue());
        } elseif ($property instanceof CompoundTagMetadataProperty) {
            $writer->writeByteArray((new ReplayNbtSerializer())->write(new TreeRoot($property->getValue()->getRoot())));
        } elseif ($property instanceof BlockPosMetadataProperty) {
            $value = $property->getValue();
            VarInt::writeSignedInt($writer, $value->getX());
            VarInt::writeSignedInt($writer, $value->getY());
            VarInt::writeSignedInt($writer, $value->getZ());
        } elseif ($property instanceof LongMetadataProperty) {
            VarInt::writeSignedLong($writer, $property->getValue());
        } elseif ($property instanceof Vec3MetadataProperty) {
            $value = $property->getValue();
            LE::writeFloat($writer, (float)$value->x);
            LE::writeFloat($writer, (float)$value->y);
            LE::writeFloat($writer, (float)$value->z);
        }
    }

    private static function readMetadataValue(ByteBufferReader $reader, int $type): MetadataProperty {
        return match ($type) {
            self::METADATA_BYTE => new ByteMetadataProperty(Byte::readSigned($reader)),
            self::METADATA_SHORT => new ShortMetadataProperty(LE::readSignedShort($reader)),
            self::METADATA_INT => new IntMetadataProperty(VarInt::readSignedInt($reader)),
            self::METADATA_FLOAT => new FloatMetadataProperty(LE::readFloat($reader)),
            self::METADATA_STRING => new StringMetadataProperty(self::readString($reader)),
            self::METADATA_COMPOUND_TAG => new CompoundTagMetadataProperty(new CacheableNbt(self::readNetworkNbtCompound($reader))),
            self::METADATA_BLOCK_POSITION => new BlockPosMetadataProperty(new BlockPosition(
                VarInt::readSignedInt($reader),
                VarInt::readSignedInt($reader),
                VarInt::readSignedInt($reader)
            )),
            self::METADATA_LONG => new LongMetadataProperty(VarInt::readSignedLong($reader)),
            self::METADATA_VECTOR3 => new Vec3MetadataProperty(new Vector3(
                LE::readFloat($reader),
                LE::readFloat($reader),
                LE::readFloat($reader)
            )),
            default => throw new UnexpectedValueException("Unknown replay metadata type $type")
        };
    }

    private static function readNetworkNbtCompound(ByteBufferReader $reader): CompoundTag {
        $offset = $reader->getOffset();
        try {
            return (new ReplayNbtSerializer())->read($reader->getData(), $offset, 512)->mustGetCompoundTag();
        } finally {
            $reader->setOffset($offset);
        }
    }

    private static function writeSkinImage(ByteBufferWriter $writer, SkinImage $image): void {
        LE::writeUnsignedInt($writer, $image->getWidth());
        LE::writeUnsignedInt($writer, $image->getHeight());
        self::writeString($writer, $image->getData());
    }

    private static function readSkinImage(ByteBufferReader $reader): SkinImage {
        $width = LE::readUnsignedInt($reader);
        $height = LE::readUnsignedInt($reader);
        return new SkinImage($height, $width, self::readString($reader));
    }

    private static function writeString(ByteBufferWriter $writer, string $value): void {
        VarInt::writeUnsignedInt($writer, strlen($value));
        $writer->writeByteArray($value);
    }

    private static function readString(ByteBufferReader $reader): string {
        return $reader->readByteArray(VarInt::readUnsignedInt($reader));
    }

    private static function writeBool(ByteBufferWriter $writer, bool $value): void {
        Byte::writeUnsigned($writer, $value ? 1 : 0);
    }

    private static function readBool(ByteBufferReader $reader): bool {
        return Byte::readUnsigned($reader) !== 0;
    }
}
