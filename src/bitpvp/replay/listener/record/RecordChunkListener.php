<?php

declare(strict_types=1);

namespace bitpvp\replay\listener\record;

use bitpvp\replay\action\type\world\SetBlockAction;
use bitpvp\replay\recorder\Recorder;
use pocketmine\math\Vector3;
use pocketmine\world\ChunkListener;
use pocketmine\world\ChunkListenerNoOpTrait;

class RecordChunkListener implements ChunkListener {
    use ChunkListenerNoOpTrait;

    public function __construct(
        private Recorder $recorder
    ) {}

    public function onBlockChanged(Vector3 $block): void {
        $this->recorder->addAction(SetBlockAction::create($this->recorder->getWorld()->getBlock($block)));
    }
}
