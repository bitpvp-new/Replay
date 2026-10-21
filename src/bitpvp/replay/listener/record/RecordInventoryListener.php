<?php

declare(strict_types=1);

namespace bitpvp\replay\listener\record;

use bitpvp\replay\action\type\entity\EntityInventoryAction;
use bitpvp\replay\recorder\Recorder;
use pocketmine\entity\Human;
use pocketmine\entity\Living;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\InventoryListener;
use pocketmine\item\Item;

final class RecordInventoryListener implements InventoryListener {

    /** @var array<int, array<int, self>> */
    private static array $listeners = [];

    public function __construct(
        private Recorder $recorder
    ) {}

    public static function addListener(Recorder $recorder, Living $living): void {
        $entityId = $living->getId();
        $recorderId = spl_object_id($recorder);
        if (isset(self::$listeners[$entityId][$recorderId])) {
            return;
        }
        $listener = new self($recorder);
        self::$listeners[$entityId][$recorderId] = $listener;
        $living->getArmorInventory()->getListeners()->add($listener);
        if ($living instanceof Human) {
            $living->getInventory()->getListeners()->add($listener);
            $living->getOffHandInventory()->getListeners()->add($listener);
        }
    }

    public static function removeListeners(Living $living, Recorder $recorder): void {
        $entityId = $living->getId();
        $recorderId = spl_object_id($recorder);
        $listener = self::$listeners[$entityId][$recorderId] ?? null;
        if ($listener === null) {
            return;
        }
        $living->getArmorInventory()->getListeners()->remove($listener);
        if ($living instanceof Human) {
            $living->getInventory()->getListeners()->remove($listener);
            $living->getOffHandInventory()->getListeners()->remove($listener);
        }
        unset(self::$listeners[$entityId][$recorderId]);
        if (self::$listeners[$entityId] === []) {
            unset(self::$listeners[$entityId]);
        }
    }

    public function onSlotChange(Inventory $inventory, int $slot, Item $oldItem): void {
        $this->record($inventory, $slot);
    }

    public function onContentChange(Inventory $inventory, array $oldContents): void {
        $this->record($inventory);
    }

    private function record(Inventory $inventory, ?int $slot = null): void {
        $holder = $inventory->getHolder();
        if (!$holder instanceof Living || !$holder->isAlive() || !$this->recorder->canRecordEntity($holder)) {
            return;
        }
        $this->recorder->addAction(EntityInventoryAction::create($holder, $inventory, $slot));
    }
}
