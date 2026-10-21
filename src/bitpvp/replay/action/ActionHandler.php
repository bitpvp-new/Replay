<?php

declare(strict_types=1);

namespace bitpvp\replay\action;

use bitpvp\replay\action\type\actor\ActorDeathAction;
use bitpvp\replay\action\type\actor\ActorDespawnAction;
use bitpvp\replay\action\type\actor\ActorEventAction;
use bitpvp\replay\action\type\actor\ActorSetMetadataAction;
use bitpvp\replay\action\type\entity\EntityArmorEquipAction;
use bitpvp\replay\action\type\entity\EntityChangeSkinAction;
use bitpvp\replay\action\type\entity\EntityEquipAction;
use bitpvp\replay\action\type\entity\EntityEffectAction;
use bitpvp\replay\action\type\entity\EntityInventoryAction;
use bitpvp\replay\action\type\entity\EntityMoveAction;
use bitpvp\replay\action\type\entity\EntityPlayEmoteAction;
use bitpvp\replay\action\type\entity\EntitySpawnAction;
use bitpvp\replay\action\type\entity\EntitySpawnLivingAction;
use bitpvp\replay\action\type\entity\EntitySpawnItemAction;
use bitpvp\replay\action\type\player\PlayerAnimationAction;
use bitpvp\replay\action\type\player\PlayerChatAction;
use bitpvp\replay\action\type\world\BlockEventAction;
use bitpvp\replay\action\type\world\LevelEventAction;
use bitpvp\replay\action\type\world\SetBlockAction;
use bitpvp\replay\action\type\world\SignChangeAction;
use bitpvp\replay\action\type\world\WorldChangeAction;
use bitpvp\replay\action\type\world\WorldChangeTimeAction;
use pocketmine\utils\SingletonTrait;

final class ActionHandler {
    use SingletonTrait {
        setInstance as private;
        reset as private;
    }

    /** @var array<int, Action> */
    private array $actions = [];

    private function __construct() {
        $this->registerAction(
            new EntitySpawnAction(),
            new EntitySpawnLivingAction(),
            new EntitySpawnItemAction(),
            new EntityMoveAction(),
            new EntityEquipAction(),
            new EntityArmorEquipAction(),
            new EntityEffectAction(),
            new EntityInventoryAction(),
            new EntityPlayEmoteAction(),
            new EntityChangeSkinAction(),

            new PlayerAnimationAction(),
            new PlayerChatAction(),

            new ActorDeathAction(),
            new ActorDespawnAction(),
            new ActorEventAction(),
            new ActorSetMetadataAction(),

            new WorldChangeAction(),
            new WorldChangeTimeAction(),
            new BlockEventAction(),
            new SetBlockAction(),
            new SignChangeAction(),
            new LevelEventAction(),
        );
    }

    public function registerAction(Action ...$actions): void {
        foreach ($actions as $action) {
            $this->actions[$action::id()] = clone $action;
        }
    }

    /**
     * @return array<int, Action>
     */
    public function getActions(): array {
        return $this->actions;
    }

    public function getAction(int $id): ?Action {
        return isset($this->actions[$id]) ? clone $this->actions[$id] : null;
    }
}
