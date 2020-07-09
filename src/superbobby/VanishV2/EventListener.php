<?php

namespace superbobby\VanishV2;

use pocketmine\event\entity\EntityCombustEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\QueryRegenerateEvent;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;

use function array_search;
use function in_array;

class EventListener implements Listener {

    private $plugin;

    public function __construct(VanishV2 $plugin) {
        $this->plugin = $plugin;
    }

    public function onQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        $name = $player->getName();
        if (in_array($name, VanishV2::$vanish)) {
            if ($this->plugin->getConfig()->get("unvanish-after-leaving") === true) {
                unset(VanishV2::$vanish[array_search($name, VanishV2::$vanish)]);
            }
        }
        if(in_array($player, VanishV2::$online, true)){
            unset(VanishV2::$online[array_search($player, VanishV2::$online, true)]);
        }
    }

    public function PickUp(InventoryPickupItemEvent $event) {
        $inv = $event->getInventory();
        $player = $inv->getHolder();
        $name = $player->getName();
        if (in_array($name, VanishV2::$vanish)) {
            $event->setCancelled();
        }
    }

    public function onDamage(EntityDamageEvent $event) {
        $player = $event->getEntity();
        if ($player instanceof Player) {
            $name = $player->getName();
            if (in_array($name, VanishV2::$vanish)) {
                if ($this->plugin->getConfig()->get("disable-damage") === true) {
                    $event->setCancelled();
                }
            }
        }
    }

    public function onPlayerBurn(EntityCombustEvent $event) {
        $player = $event->getEntity();
        if ($player instanceof Player) {
            $name = $player->getName();
            if (in_array($name, VanishV2::$vanish)) {
                if ($this->plugin->getConfig()->get("disable-damage") === true) {
                    $event->setCancelled();
                }
            }
        }
    }

    public function onJoin(PlayerJoinEvent $event){
            $player = $event->getPlayer();
            if(!in_array($player->getName(), VanishV2::$vanish)){
                if(!in_array($player, VanishV2::$online, true)) {
                    VanishV2::$online[] = $player;
                }
            }
    }

    public function onQuery(QueryRegenerateEvent $event) {
        $event->setPlayerList(VanishV2::$online);
        foreach (Server::getInstance()->getOnlinePlayers() as $p) {
            if(in_array($p->getName(), VanishV2::$vanish)) {
                    $online = $event->getPlayerCount();
                    $event->setPlayerCount($online - 1);
            }
        }
    }
}