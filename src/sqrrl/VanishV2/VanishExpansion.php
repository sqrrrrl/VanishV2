<?php

namespace sqrrl\VanishV2;

use pocketmine\player\Player;
use MohamadRZ4\Placeholder\expansion\PlaceholderExpansion;

class VanishExpansion extends PlaceholderExpansion
{
    public function getIdentifier(): string
    {
        return "vanishv2";
    }

    public function getAuthor(): string
    {
        return "sqrrl";
    }

    public function getVersion(): string
    {
        return "1.0.0";
    }

    public function onPlaceholderRequest(?Player $player, string $params): ?string
    {
        if ($player === null) return null;

        switch($params){
            case "fake_count":
                return strval(count(VanishV2::$online));
            default:
                return null;
        }
    }
}
