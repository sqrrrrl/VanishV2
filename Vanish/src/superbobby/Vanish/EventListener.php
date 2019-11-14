<?php

namespace superbobby\Vanish;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;

use function array_search;
use function in_array;

class EventListener implements Listener {

    public function onQuit(PlayerQuitEvent $event){
        $player = $event->getPlayer();
        $name = $player->getName();
        if(in_array($name, Vanish::$vanish)) unset(Vanish::$vanish[array_search($name, Vanish::$vanish)]);
    }
}
