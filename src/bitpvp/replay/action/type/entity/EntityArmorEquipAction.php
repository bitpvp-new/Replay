<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\entity;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\EntityAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use pocketmine\entity\Living;
use pocketmine\item\Item;

class EntityArmorEquipAction extends EntityAction {

    protected const int ID = ActionIds::ENTITY_ARMOR_EQUIP;

    /** @var array<int, Item> */
    private array $contents = [];

    public static function create(int $entityId, array $contents): self {
        $action = new self();
        $action->entityId = $entityId;
        $action->contents = $contents;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        parent::serialize($stream);
        $stream->putContents($this->contents);
    }

    public function deserialize(BinaryStream $stream): void {
        parent::deserialize($stream);
        $this->contents = $stream->getContents();
    }

    public function handle(ReplayPlayer $replay): void {
        $entity = $replay->getEntity($this->entityId);
        if ($entity instanceof Living) {
            $entity->getArmorInventory()->setContents($this->contents);
        }
    }

    public function handleReversed(ReplayPlayer $replay): ?Action {
        $entity = $replay->getEntity($this->entityId);
        if ($entity instanceof Living) {
            return self::create($this->entityId, $entity->getArmorInventory()->getContents());
        }
        return null;
    }
}
