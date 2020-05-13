<?php

namespace superbobby\VanishV2;

use pocketmine\event\Listener;
use pocketmine\Server;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;

use function array_search;
use function in_array;

class EventListener implements Listener {

    public function onQuit(PlayerQuitEvent $event){
        $player = $event->getPlayer();
        $name = $player->getName();
        if(in_array($name, VanishV2::$vanish)){
            unset(VanishV2::$vanish[array_search($name, VanishV2::$vanish)]);
        }
    }
    public function PickUp(InventoryPickupItemEvent $event){
        foreach (Server::getInstance()->getOnlinePlayers() as $p) {
            $name = $p->getName();
            if (in_array($name, VanishV2::$vanish)) {
                $event->setCancelled();
            }
        }
    }
}
