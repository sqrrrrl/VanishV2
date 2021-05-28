<?php

namespace superbobby\VanishV2;

use pocketmine\block\Block;
use pocketmine\event\entity\EntityCombustEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\QueryRegenerateEvent;
use pocketmine\inventory\DoubleChestInventory;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;
use muqsit\invmenu\InvMenu;
use Ifera\ScoreHud\event\PlayerTagUpdateEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;

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
        if(in_array($name, VanishV2::$vanish)) {
            if($this->plugin->getConfig()->get("unvanish-after-leaving") === true) {
                unset(VanishV2::$vanish[array_search($name, VanishV2::$vanish)]);
            }
        }
        if(in_array($player, VanishV2::$online, true)){
            unset(VanishV2::$online[array_search($player, VanishV2::$online, true)]);
            if($this->plugin->newScorehud == true){
                foreach($this->plugin->getServer()->getOnlinePlayers() as $players) {
                    if ($players->isOnline()) {
                        if (!$players->hasPermission('vanish.see')) {
                            $ev = new PlayerTagUpdateEvent($players, new ScoreTag("VanishV2.fake_count", strval(count(VanishV2::$online))));
                            $ev->call();
                        }else{
                            $ev = new PlayerTagUpdateEvent($players, new ScoreTag("VanishV2.fake_count", strval(count($this->plugin->getServer()->getOnlinePlayers()))));
                            $ev->call();
                        }
                    }
                }
            }
        }
    }

    public function PickUp(InventoryPickupItemEvent $event) {
        $inv = $event->getInventory();
        $player = $inv->getHolder();
        $name = $player->getName();
        if(in_array($name, VanishV2::$vanish)) {
            $event->setCancelled();
        }
    }

    public function onDamage(EntityDamageEvent $event) {
        $player = $event->getEntity();
        if($player instanceof Player) {
            $name = $player->getName();
            if(in_array($name, VanishV2::$vanish)) {
                if($this->plugin->getConfig()->get("disable-damage") === true) {
                    $event->setCancelled();
                }
            }
        }
    }

    public function onPlayerBurn(EntityCombustEvent $event) {
        $player = $event->getEntity();
        if($player instanceof Player) {
            $name = $player->getName();
            if(in_array($name, VanishV2::$vanish)) {
                if($this->plugin->getConfig()->get("disable-damage") === true) {
                    $event->setCancelled();
                }
            }
        }
    }

    public function onExhaust(PlayerExhaustEvent $event) {
        $player = $event->getPlayer();
        if(in_array($player->getName(), VanishV2::$vanish)){
            if($this->plugin->getConfig()->get("hunger") === false){
                $event->setCancelled();
            }
        }
    }

    public function onJoin(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        if(!in_array($player->getName(), VanishV2::$vanish)){
            if(!in_array($player, VanishV2::$online, true)) {
                VanishV2::$online[] = $player;
                if($this->plugin->newScorehud == true){
                    foreach($this->plugin->getServer()->getOnlinePlayers() as $players) {
                        if($players->isOnline()) {
                            if(!$players->hasPermission('vanish.see')) {
                                $ev = new PlayerTagUpdateEvent($players, new ScoreTag("VanishV2.fake_count", strval(count(VanishV2::$online))));
                                $ev->call();
                            }else{
                                $ev = new PlayerTagUpdateEvent($players, new ScoreTag("VanishV2.fake_count", strval(count($this->plugin->getServer()->getOnlinePlayers()))));
                                $ev->call();
                            }
                        }
                    }
                }
            }
        }
    }

    public function onQuery(QueryRegenerateEvent $event) {
        $event->setPlayerList(VanishV2::$online);
        foreach(Server::getInstance()->getOnlinePlayers() as $p) {
            if(in_array($p->getName(), VanishV2::$vanish)) {
                    $online = $event->getPlayerCount();
                    $event->setPlayerCount($online - 1);
            }
        }
    }

    public function onInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock()->getId();
        $chest = $event->getBlock();
        $tile = $chest->getLevel()->getTile(new Vector3($chest->x, $chest->y, $chest->z));
        $action = $event->getAction();
        if(in_array($player->getName(), VanishV2::$vanish)) {
            if($this->plugin->getConfig()->get("silent-chest") === true) {
                if($block === Block::CHEST or $block === Block::TRAPPED_CHEST) {
                    if($action === $event::RIGHT_CLICK_BLOCK) {
                        if(!$player->isSneaking()) {
                            $event->setCancelled();
                            $name = $tile->getName();
                            $inv = $tile->getInventory();
                            $content = $tile->getInventory()->getContents();
                            if($content != null) {
                                if($inv instanceof DoubleChestInventory) {
                                    $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
                                }else{
                                    $menu = InvMenu::create(InvMenu::TYPE_CHEST);
                                }
                                $menu->getInventory()->setContents($content);
                                $menu->setListener(InvMenu::readonly());
                                $menu->setName($name);
                                $menu->send($player);
                            }else{
                                $player->sendMessage(VanishV2::PREFIX . TextFormat::RED . "This chest is empty");
                            }
                        }
                    }else{
                        $event->setCancelled();
                    }
                }
            }
        }
    }
}