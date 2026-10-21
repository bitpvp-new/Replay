<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\entity;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\EntityAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use bitpvp\replay\utils\EffectUtils;
use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\Living;
use pocketmine\color\Color;

final class EntityEffectAction extends EntityAction {

    protected const int ID = ActionIds::ENTITY_EFFECT;

    private string $effectAlias = "";
    /** @var array{duration: int, amplifier: int, visible: bool, ambient: bool, color: int, infinite: bool}|null */
    private ?array $state = null;

    public static function create(int $entityId, EffectInstance $effect): ?self {
        $alias = EffectUtils::findAlias($effect->getType());
        if ($alias === null) {
            return null;
        }
        return self::createState($entityId, $alias, self::snapshot($effect));
    }

    public static function createRemoved(int $entityId, Effect $effect): ?self {
        $alias = EffectUtils::findAlias($effect);
        return $alias === null ? null : self::createState($entityId, $alias, null);
    }

    /** @param array{duration: int, amplifier: int, visible: bool, ambient: bool, color: int, infinite: bool}|null $state */
    private static function createState(int $entityId, string $alias, ?array $state): self {
        $action = new self();
        $action->entityId = $entityId;
        $action->effectAlias = $alias;
        $action->state = $state;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        parent::serialize($stream);
        $stream->putString($this->effectAlias);
        $stream->putBool($this->state !== null);
        if ($this->state !== null) {
            $stream->putInt($this->state["duration"]);
            $stream->putInt($this->state["amplifier"]);
            $stream->putBool($this->state["visible"]);
            $stream->putBool($this->state["ambient"]);
            $color = $this->state["color"];
            $stream->putInt($color > 0x7fffffff ? $color - 0x100000000 : $color);
            $stream->putBool($this->state["infinite"]);
        }
    }

    public function deserialize(BinaryStream $stream): void {
        parent::deserialize($stream);
        $this->effectAlias = $stream->getString();
        $this->state = $stream->getBool() ? [
            "duration" => $stream->getInt(),
            "amplifier" => $stream->getInt(),
            "visible" => $stream->getBool(),
            "ambient" => $stream->getBool(),
            "color" => $stream->getInt(),
            "infinite" => $stream->getBool(),
        ] : null;
    }

    public function handle(ReplayPlayer $replay): void {
        $entity = $replay->getEntity($this->entityId);
        $effectType = StringToEffectParser::getInstance()->parse($this->effectAlias);
        if (!$entity instanceof Living || $effectType === null) {
            return;
        }
        $entity->getEffects()->remove($effectType);
        if ($this->state !== null) {
            $state = $this->state;
            $effect = new EffectInstance(
                $effectType,
                $state["duration"],
                $state["amplifier"],
                $state["visible"],
                $state["ambient"],
                Color::fromRGBA($state["color"]),
                $state["infinite"]
            );
            $entity->getEffects()->add($effect);
        }
        $entity->scheduleUpdate();
    }

    public function handleReversed(ReplayPlayer $replay): ?Action {
        $effectType = StringToEffectParser::getInstance()->parse($this->effectAlias);
        $entity = $replay->getEntity($this->entityId);
        if (!$entity instanceof Living || $effectType === null) {
            return null;
        }
        $current = $entity->getEffects()->get($effectType);
        return self::createState($this->entityId, $this->effectAlias, $current === null ? null : self::snapshot($current));
    }

    /** @return array{duration: int, amplifier: int, visible: bool, ambient: bool, color: int, infinite: bool} */
    private static function snapshot(EffectInstance $effect): array {
        return [
            "duration" => $effect->getDuration(),
            "amplifier" => $effect->getAmplifier(),
            "visible" => $effect->isVisible(),
            "ambient" => $effect->isAmbient(),
            "color" => $effect->getColor()->toRGBA(),
            "infinite" => $effect->isInfinite(),
        ];
    }

}
