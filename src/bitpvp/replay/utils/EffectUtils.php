<?php

declare(strict_types=1);

namespace bitpvp\replay\utils;

use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\lang\Translatable;

final class EffectUtils {

    /** @var array<string, string> */
    private static array $aliasesByType = [];

    /** @var list<string>|null */
    private static ?array $cachedParserAliases = null;

    private function __construct() {}

    public static function findAlias(Effect $effect): ?string {
        $parser = StringToEffectParser::getInstance();
        $parserAliases = $parser->getKnownAliases();

        if (self::$cachedParserAliases !== $parserAliases) {
            self::rebuildAliasIndex($parser, $parserAliases);
        }

        return self::$aliasesByType[self::getTypeKey($effect)] ?? null;
    }

    /** @param list<string> $parserAliases */
    private static function rebuildAliasIndex(StringToEffectParser $parser, array $parserAliases): void {
        self::$aliasesByType = [];

        foreach (VanillaEffects::getAll() as $alias => $effect) {
            self::$aliasesByType[self::getTypeKey($effect)] ??= (string)$alias;
        }

        foreach ($parserAliases as $alias) {
            $effect = $parser->parse($alias);
            if ($effect !== null) {
                self::$aliasesByType[self::getTypeKey($effect)] ??= $alias;
            }
        }

        self::$cachedParserAliases = $parserAliases;
    }

    private static function getTypeKey(Effect $effect): string {
        $name = $effect->getName();
        return $effect::class . "\0" . ($name instanceof Translatable ? $name->getText() : $name);
    }
}
