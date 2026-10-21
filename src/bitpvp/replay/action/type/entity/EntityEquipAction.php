<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\entity;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\EntityAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use pocketmine\entity\Human;
use pocketmine\inventory\InventoryHolder;
use pocketmine\item\Item;

class EntityEquipAction extends EntityAction {

    protected const int ID = ActionIds::ENTITY_EQUIP;

    private Item $item;

    public static function create(int $entityId, Item $item): self {
        $action = new self();
        $action->entityId = $entityId;
        $action->item = $item;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        parent::serialize($stream);
        $stream->putItem($this->item);
    }

    public function deserialize(BinaryStream $stream): void {
        parent::deserialize($stream);
        $this->item = $stream->getItem();
    }

    public function handle(ReplayPlayer $replay): void {
        $entity = $replay->getEntity($this->entityId);
        if ($entity instanceof Human) {
            $entity->getInventory()->setItemInHand($this->item);
        } elseif ($entity instanceof InventoryHolder) {
            $entity->getInventory()->setItem(0, $this->item);
        }
    }

    public function handleReversed(ReplayPlayer $replay): ?Action {
        $entity = $replay->getEntity($this->entityId);
        if ($entity instanceof Human) {
            return self::create($this->entityId, $entity->getInventory()->getItemInHand());
        }
        return null;
    }
}
