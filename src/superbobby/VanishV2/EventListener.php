<?php

namespace superbobby\VanishV2;

use pocketmine\block\Block;
use pocketmine\event\entity\EntityCombustEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
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
use pocketmine\scheduler\ClosureTask;
use muqsit\invmenu\InvMenu;

use function array_search;
use function in_array;

class EventListener implements Listener {

    private VanishV2 $plugin;

    public function __construct(VanishV2 $plugin) {
        $this->plugin = $plugin;
    }

    public function onQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        $name = $player->getName();
        if(in_array($name, VanishV2::$vanish)) {
            if($this->plugin->getConfig()->get("unvanish-after-leaving")) {
                unset(VanishV2::$vanish[array_search($name, VanishV2::$vanish)]);
            }
        }
        if(in_array($player, VanishV2::$online, true)){
            unset(VanishV2::$online[array_search($player, VanishV2::$online, true)]);
            $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $i): void{
                $this->plugin->updateHudPlayerCount();
            }), 20);
        }
    }

    public function pickUp(InventoryPickupItemEvent $event) {
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
                if($this->plugin->getConfig()->get("disable-damage")) {
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
                if($this->plugin->getConfig()->get("disable-damage")) {
                    $event->setCancelled();
                }
            }
        }
    }

    public function onExhaust(PlayerExhaustEvent $event) {
        $player = $event->getPlayer();
        if(in_array($player->getName(), VanishV2::$vanish)){
            if(!$this->plugin->getConfig()->get("hunger")){
                $event->setCancelled();
            }
        }
    }

    public function onJoin(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        if(!in_array($player->getName(), VanishV2::$vanish)){
            if(!in_array($player, VanishV2::$online, true)) {
                VanishV2::$online[] = $player;
                $this->plugin->updateHudPlayerCount();
            }
        }
    }

    /**
     * @param PlayerJoinEvent $event
     * @priority HIGHEST
     */
    public function setNametag(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        if (in_array($player->getName(), VanishV2::$vanish)){
            $player->setNameTag(TextFormat::GOLD . "[V] " . TextFormat::RESET . $player->getNameTag());
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
            if($this->plugin->getConfig()->get("silent-chest")) {
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

    /**
     * @param PlayerJoinEvent $event
     * @priority HIGHEST
     */
    public function silentJoin(PlayerJoinEvent $event) {
        if ($event->getPlayer()->hasPermission("vanish.silent")) {
            if ($this->plugin->getConfig()->get("silent-join-leave")["join"]) {
                if (!$this->plugin->getConfig()->get("silent-join-leave")["vanished-only"]) {
                    $event->setJoinMessage(null);
                }else{
                    if (in_array($event->getPlayer()->getName(), VanishV2::$vanish)){
                        $event->setJoinMessage(null);
                    }
                }
            }
        }
    }

    /**
     * @param PlayerQuitEvent $event
     * @priority HIGHEST
     */
    public function silentLeave(PlayerQuitEvent $event) {
        if ($event->getPlayer()->hasPermission("vanish.silent")) {
            if ($this->plugin->getConfig()->get("silent-join-leave")["leave"]) {
                if (!$this->plugin->getConfig()->get("silent-join-leave")["vanished-only"]) {
                    $event->setQuitMessage(null);
                }else{
                    if (in_array($event->getPlayer()->getName(), VanishV2::$vanish)){
                        $event->setQuitMessage(null);
                    }
                }
            }
        }
    }

    public function onCommandExecute(PlayerCommandPreprocessEvent $event){
        $sender = $event->getPlayer();
        if (!$this->plugin->getConfig()->get("can-send-msg")) {
            $message = $event->getMessage();
            $message = explode(" ", $message);
            $command = array_shift($message);
            if (in_array(strtolower($command), array("/tell", "/msg", "/w"))) {
                $receiver = $this->plugin->getServer()->getPlayer(array_shift($message));
                if ($receiver and in_array($receiver->getName(), VanishV2::$vanish) and !$sender->hasPermission("vanish.see") and $sender !== $receiver) {
                    $event->setCancelled();
                    $sender->sendMessage($this->plugin->getConfig()->get("messages")["sender-error"]);
                    $receiver->sendMessage(VanishV2::PREFIX . str_replace(array("%sender", "%message"), array($sender->getName(), implode(" ", $message)), $this->plugin->getConfig()->get("messages")["receiver-message"]));
                }
            }else{
                if ($this->plugin->getConfig()->get("additional-commands")) {
                    $command = substr($command, 1);
                    if (array_key_exists(strtolower($command), $this->plugin->getConfig()->get("additional-commands"))) {
                        $receiver = $this->plugin->getServer()->getPlayer(array_shift($message));
                        if ($receiver and in_array($receiver->getName(), VanishV2::$vanish) and !$sender->hasPermission("vanish.see") and $sender !== $receiver) {
                            $event->setCancelled();
                            $sender->sendMessage($this->plugin->getConfig()->get("additional-commands")[$command]["sender-error"]);
                            $receiver->sendMessage(VanishV2::PREFIX . str_replace("%sender", $sender->getName(), $this->plugin->getConfig()->get("additional-commands")[$command]["receiver-message"]));
                        }
                    }
                }
            }
        }
    }
}