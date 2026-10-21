<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\entity;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\EntityAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use pocketmine\entity\Human;
use pocketmine\entity\Living;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;

final class EntityInventoryAction extends EntityAction {

    protected const int ID = ActionIds::ENTITY_INVENTORY;

    /** @var array<int, Item> */
    private array $items = [];
    /** @var array<int, Item> */
    private array $armor = [];
    private ?Item $offHand = null;
    private ?int $heldSlot = null;

    public static function create(Living $entity, ?Inventory $changedInventory = null, ?int $changedSlot = null, ?int $heldSlot = null): self {
        $action = new self();
        $action->entityId = $entity->getId();
        $action->armor = $entity->getArmorInventory()->getContents();
        if ($entity instanceof Human) {
            $action->items = $entity->getInventory()->getContents();
            $action->offHand = $entity->getOffHandInventory()->getItem(0);
            $action->heldSlot = $heldSlot ?? $entity->getInventory()->getHeldItemIndex();
            if ($changedInventory === $entity->getInventory() && $changedSlot !== null) {
                $action->items[$changedSlot] = $changedInventory->getItem($changedSlot);
            } elseif ($changedInventory === $entity->getOffHandInventory()) {
                $action->offHand = $entity->getOffHandInventory()->getItem(0);
            }
        }
        if ($changedInventory === $entity->getArmorInventory() && $changedSlot !== null) {
            $action->armor[$changedSlot] = $changedInventory->getItem($changedSlot);
        }
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        parent::serialize($stream);
        $stream->putContents($this->items);
        $stream->putContents($this->armor);
        $stream->putBool($this->offHand !== null);
        if ($this->offHand !== null) {
            $stream->putItem($this->offHand);
        }
        $stream->putBool($this->heldSlot !== null);
        if ($this->heldSlot !== null) {
            $stream->putInt($this->heldSlot);
        }
    }

    public function deserialize(BinaryStream $stream): void {
        parent::deserialize($stream);
        $this->items = $stream->getContents();
        $this->armor = $stream->getContents();
        $this->offHand = $stream->getBool() ? $stream->getItem() : null;
        $this->heldSlot = $stream->getBool() ? $stream->getInt() : null;
    }

    public function handle(ReplayPlayer $replay): void {
        $entity = $replay->getEntity($this->entityId);
        if ($entity instanceof Living) {
            $entity->getArmorInventory()->setContents($this->armor);
        }
        if ($entity instanceof Human) {
            $entity->getInventory()->setContents($this->items);
            if ($this->heldSlot !== null) {
                $entity->getInventory()->setHeldItemIndex($this->heldSlot);
            }
            if ($this->offHand !== null) {
                $entity->getOffHandInventory()->setItem(0, $this->offHand);
            }
        }
    }

    public function handleReversed(ReplayPlayer $replay): ?Action {
        $entity = $replay->getEntity($this->entityId);
        return $entity instanceof Living ? self::create($entity) : null;
    }
}
