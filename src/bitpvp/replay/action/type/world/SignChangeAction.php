<?php

declare(strict_types=1);

namespace bitpvp\replay\action\type\world;

use bitpvp\replay\action\Action;
use bitpvp\replay\action\ActionIds;
use bitpvp\replay\action\type\WorldAction;
use bitpvp\replay\replayer\ReplayPlayer;
use bitpvp\replay\utils\BinaryStream;
use pocketmine\block\BaseSign;
use pocketmine\block\utils\SignText;
use pocketmine\color\Color;
use pocketmine\math\Vector3;

class SignChangeAction extends WorldAction {

    protected const int ID = ActionIds::SIGN_CHANGE;

    private Vector3 $position;
    private SignText $text;

    public static function create(BaseSign $sign): self {
        $action = new self();
        $action->position = $sign->getPosition();
        $action->text = $sign->getText();
        return $action;
    }

    public function serialize(BinaryStream $stream): void {
        $stream->putVector3($this->position);
        $stream->putString(implode("\n", $this->text->getLines()));
        $stream->putBool($this->text->isGlowing());
        $color = $this->text->getBaseColor()->toRGBA();
        $stream->putInt($color > 0x7fffffff ? $color - 0x100000000 : $color);
    }

    public function deserialize(BinaryStream $stream): void {
        $this->position = $stream->getVector3();
        $lines = $stream->getString();
        $glowing = $stream->getBool();
        $color = Color::fromRGBA($stream->getInt());
        $this->text = SignText::fromBlob($lines, $color, $glowing);
    }

    public function handle(ReplayPlayer $replay): void {
        $block = $replay->getWorld()->getBlock($this->position);
        if ($block instanceof BaseSign) {
            $replay->getWorld()->setBlock($this->position, $block->setText($this->text));
        }
    }

    public function handleReversed(ReplayPlayer $replay): ?Action {
        $block = $replay->getWorld()->getBlock($this->position);
        return $block instanceof BaseSign ? self::create($block) : parent::handleReversed($replay);
    }
}
