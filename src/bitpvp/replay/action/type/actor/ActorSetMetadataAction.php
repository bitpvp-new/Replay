<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\actor;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\EntityAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use pocketmine\network\mcpe\protocol\types\entity\MetadataProperty;

class ActorSetMetadataAction extends EntityAction {

    protected const int ID = ActionIds::SET_METADATA;

    /** @var array<int, MetadataProperty> */
    private array $metadata = [];

    public static function create(int $entityId, array $metadata): self {
        $action = new self();
        $action->entityId = $entityId;
        $action->metadata = $metadata;
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        parent::serialize($stream);
        $stream->putEntityMetadata($this->metadata);
    }

    public function deserialize(BinaryStream $stream): void {
        parent::deserialize($stream);
        $this->metadata = $stream->getEntityMetadata();
    }

    public function handle(ReplayPlayer $replay): void {
        $entity = $replay->getEntity($this->entityId);
        if ($entity === null) {
            return;
        }
        $properties = $entity->getNetworkProperties();
        foreach ($this->metadata as $key => $property) {
            $properties->set($key, $property);
        }
    }

    public function handleReversed(ReplayPlayer $replay): ?Action {
        $entity = $replay->getEntity($this->entityId);
        if ($entity === null) {
            return null;
        }
        return self::create($this->entityId, $entity->getNetworkProperties()->getAll());
    }
}
