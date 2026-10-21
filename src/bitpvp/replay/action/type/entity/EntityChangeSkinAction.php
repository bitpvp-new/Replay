<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\entity;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\EntityAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use pocketmine\entity\Human;
use pocketmine\entity\Skin;
use pocketmine\network\mcpe\convert\TypeConverter;

class EntityChangeSkinAction extends EntityAction {

    protected const int ID = ActionIds::ENTITY_CHANGE_SKIN;

    private Skin $skin;

    public static function create(int $entityId, Skin $skin): self {
        $action = new self();
        $action->entityId = $entityId;
        $action->skin = $skin;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        parent::serialize($stream);
        $stream->putSkin(TypeConverter::getInstance()->getSkinAdapter()->toSkinData($this->skin));
    }

    public function deserialize(BinaryStream $stream): void {
        parent::deserialize($stream);
        $this->skin = TypeConverter::getInstance()->getSkinAdapter()->fromSkinData($stream->getSkin());
    }

    public function handle(ReplayPlayer $replay): void {
        $entity = $replay->getEntity($this->entityId);
        if ($entity instanceof Human) {
            $entity->setSkin($this->skin);
            $entity->sendSkin();
        }
    }

    public function handleReversed(ReplayPlayer $replay): ?Action {
        $entity = $replay->getEntity($this->entityId);
        if ($entity instanceof Human) {
            return self::create($this->entityId, $entity->getSkin());
        }
        return null;
    }
}
