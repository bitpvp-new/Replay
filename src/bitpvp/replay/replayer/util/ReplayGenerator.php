<?php

declare(strict_types=1);

namespace bitpvp\replay\replayer\util;

use pocketmine\world\ChunkManager;
use pocketmine\world\generator\Generator;

class ReplayGenerator extends Generator {

    public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ): void {}

    public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ): void {}
}
