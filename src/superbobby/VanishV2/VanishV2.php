<?php

namespace superbobby\VanishV2;

use pocketmine\utils\TextFormat;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\SkinAdapterSingleton;
use Ifera\ScoreHud\event\PlayerTagUpdateEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;

use function array_search;
use function in_array;
use function strtolower;

class VanishV2 extends PluginBase {
    public const PREFIX = TextFormat::BLUE . "VanishV2 " . TextFormat::DARK_GRAY . "» ". TextFormat::RESET;

    public static $vanish = [];

    public static $nametagg = [];

    public static $online = [];

    public static $AllowCombatFly = []; //for BlazinFly compatibility

    public $pk;

    public $newScorehud; //ScoreHud >= 6.0.0 compatibility

    protected static $main;

    public function onEnable() {
        self::$main = $this;
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->getScheduler()->scheduleRepeatingTask(new VanishV2Task(), 20);
        if($this->getServer()->getPluginManager()->getPlugin('ScoreHud')){
            $scorehud_version = floatval($this->getServer()->getPluginManager()->getPlugin('ScoreHud')->getDescription()->getVersion());
            if($scorehud_version >= 6.0){
                $this->getServer()->getPluginManager()->registerEvents(new TagResolveListener, $this);
                $this->newScorehud = true;
            }else{
                $this->newScorehud = false;
            }
        }else{
            $this->newScorehud = false;
        }
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        if(!class_exists(InvMenuHandler::class)){
            $this->getLogger()->error("InvMenu virion not found download VanishV2 on poggit or download InvMenu with DEVirion (not recommended)");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
        if($this->getConfig()->get("config-version") < 4 or $this->getConfig()->get("config-version") == null){
            $this->getLogger()->error("Your configuration file is outdated you have to delete it to get the new config");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
        if(class_exists(InvMenuHandler::class)) {
            if(!InvMenuHandler::isRegistered()) {
                InvMenuHandler::register($this);
            }
        }
    }

    public static function getMain(): self{
        return self::$main;
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool{
        $name = $sender->getName();
        switch(strtolower($cmd->getName())){
		case "vanish":
		case "v":
	        if(!$sender instanceof Player){
                $sender->sendMessage(self::PREFIX . TextFormat::RED . "Use this command InGame.");
                return false;
	        }

	        if(count($args) > 1){
	            $sender->sendMessage(self::PREFIX . TextFormat::RED . "Usage: /vanish <player>");
	            return false;
            	}

	        if(!$sender->hasPermission("vanish.use")){
		        $sender->sendMessage(TextFormat::RED . "You do not have permission to use this command");
                return false;
	        }

	        if(count($args) === 1){
	            if(!$sender->hasPermission("vanish.use.other")){
	                $sender->sendMessage(self::PREFIX . TextFormat::RED . "You do not have permission to vanish other players");
	                return false;
                }
            }

            if (count($args) === 0) {
                if(!in_array($name, self::$vanish)) {
                    self::$vanish[] = $name;
                    unset(self::$online[array_search($sender, self::$online, True)]);
                    $sender->sendMessage(self::PREFIX . $this->getConfig()->get("vanish-message"));
                    $nameTag = $sender->getNameTag();
                    self::$nametagg[$name] = $nameTag;
                    $sender->setNameTag("§6[V]§r $nameTag");
                    if($this->newScorehud == true){
                        foreach($this->getServer()->getOnlinePlayers() as $players) {
                            if ($players->isOnline()) {
                                if(!$players->hasPermission("vanish.see")) {
                                    $ev = new PlayerTagUpdateEvent($players, new ScoreTag("VanishV2.fake_count", strval(count(self::$online))));
                                    $ev->call();
                                }else{
                                    $ev = new PlayerTagUpdateEvent($players, new ScoreTag("VanishV2.fake_count", strval(count($this->getServer()->getOnlinePlayers()))));
                                    $ev->call();
                                }
                            }
                        }
                    }
                    if($this->getConfig()->get("enable-leave") === true) {
                        $msg = $this->getConfig()->get("FakeLeave-message");
                        $msg = str_replace("%name", "$name", $msg);
                        $this->getServer()->broadcastMessage($msg);
                    }
                    if($this->getConfig()->get("enable-fly") === true) {
                        if($sender->getGamemode() === 0) {
                            self::$AllowCombatFly[] = $name; //for BlazinFly compatibility
                            $sender->setFlying(true);
                            $sender->setAllowFlight(true);
                        }
                    }
                    foreach ($this->getServer()->getOnlinePlayers() as $players) {
                        if($players->hasPermission("vanish.see")) {
                            $msg = $this->getConfig()->get("vanish");
                            $msg = str_replace("%name", "$name", $msg);
                            $players->sendMessage($msg);
                        }
                    }
                }else{
                    unset(self::$vanish[array_search($name, self::$vanish)]);
                    self::$online[] = $sender;
                    foreach ($this->getServer()->getOnlinePlayers() as $players) {
                        $players->showPlayer($sender);
                        $nameTag = self::$nametagg[$name];
                        $sender->setNameTag("$nameTag");
                        if ($players->hasPermission("vanish.see")) {
                            $msg = $this->getConfig()->get("unvanish");
                            $msg = str_replace("%name", "$name", $msg);
                            $players->sendMessage($msg);
                        }
                    }
                    if($this->newScorehud == true){
                        foreach($this->getServer()->getOnlinePlayers() as $players) {
                            if ($players->isOnline()) {
                                if(!$players->hasPermission('vanish.see')) {
                                    $ev = new PlayerTagUpdateEvent($players, new ScoreTag("VanishV2.fake_count", strval(count(self::$online))));
                                    $ev->call();
                                }else{
                                    $ev = new PlayerTagUpdateEvent($players, new ScoreTag("VanishV2.fake_count", strval(count($this->getServer()->getOnlinePlayers()))));
                                    $ev->call();
                                }
                            }
                        }
                    }
                    if($this->getConfig()->get("enable-fly") === true) {
                        if($sender->getGamemode() === 0) {
                            unset(self::$AllowCombatFly[array_search($name, self::$AllowCombatFly)]); //for BlazinFly compatibility
                            $sender->setFlying(false);
                            $sender->setAllowFlight(false);
                        }
                    }
                    $pk = new PlayerListPacket();
                    $pk->type = PlayerListPacket::TYPE_ADD;
                    $pk->entries[] = PlayerListEntry::createAdditionEntry(
                        $sender->getUniqueId(),
                        $sender->getId(),
                        $sender->getDisplayName(),
                        SkinAdapterSingleton::get()->toSkinData($sender->getSkin()),
                        $sender->getXuid());
                    foreach($this->getServer()->getOnlinePlayers() as $p) {
                        $p->sendDataPacket($pk);
                    }
                    if($this->getConfig()->get("enable-join") === true) {
                        $msg = $this->getConfig()->get("FakeJoin-message");
                        $msg = str_replace("%name", "$name", $msg);
                        $this->getServer()->broadcastMessage($msg);
                    }
                    $sender->sendMessage(self::PREFIX . $this->getConfig()->get("unvanish-message"));
                }
            }
            if(count($args) === 1){
                $player = $this->getServer()->getPlayer($args[0]);
                if($player != null) {
                    $name = $player->getName();
                    $othername = $sender->getName();
                    if(!in_array($name, self::$vanish)) {
                        self::$vanish[] = $name;
                        unset(self::$online[array_search($player, self::$online, True)]);
                        $msg = $this->getConfig()->get("vanish-other");
                        $msg = str_replace("%name", "$name", $msg);
                        $sender->sendMessage(self::PREFIX . $msg);
                        $msg = $this->getConfig()->get("vanished-other");
                        $msg = str_replace("%other-name", "$othername", $msg);
                        $player->sendMessage(self::PREFIX . $msg);
                        $nameTag = $player->getNameTag();
                        self::$nametagg[$name] = $nameTag;
                        $player->setNameTag("§6[V]§r $nameTag");
                        if($this->newScorehud == true){
                            foreach($this->getServer()->getOnlinePlayers() as $players) {
                                if($players->isOnline()) {
                                    if(!$players->hasPermission("vanish.see")) {
                                        $ev = new PlayerTagUpdateEvent($players, new ScoreTag("VanishV2.fake_count", strval(count(self::$online))));
                                        $ev->call();
                                    }else{
                                        $ev = new PlayerTagUpdateEvent($players, new ScoreTag("VanishV2.fake_count", strval(count($this->getServer()->getOnlinePlayers()))));
                                        $ev->call();
                                    }
                                }
                            }
                        }
                        if($this->getConfig()->get("enable-leave") === true) {
                            $msg = $this->getConfig()->get("FakeLeave-message");
                            $msg = str_replace("%name", "$name", $msg);
                            $this->getServer()->broadcastMessage($msg);
                        }
                        if($this->getConfig()->get("enable-fly") === true) {
                            if($player->getGamemode() === 0) {
                                self::$AllowCombatFly[] = $name; //for BlazinFly compatibility
                                $player->setFlying(true);
                                $player->setAllowFlight(true);
                            }
                        }
                        foreach ($this->getServer()->getOnlinePlayers() as $players) {
                            if($players->hasPermission("vanish.see")) {
                                $msg = $this->getConfig()->get("vanish");
                                $msg = str_replace("%name", "$name", $msg);
                                $players->sendMessage($msg);
                            }
                        }
                    }else{
                        unset(self::$vanish[array_search($name, self::$vanish)]);
                        self::$online[] = $player;
                        foreach ($this->getServer()->getOnlinePlayers() as $players) {
                            $players->showPlayer($player);
                            $nameTag = self::$nametagg[$name];
                            $player->setNameTag("$nameTag");
                            if($players->hasPermission("vanish.see")) {
                                $msg = $this->getConfig()->get("unvanish");
                                $msg = str_replace("%name", "$name", $msg);
                                $players->sendMessage($msg);
                            }
                        }
                        if($this->newScorehud == true){
                            foreach($this->getServer()->getOnlinePlayers() as $players) {
                                if ($players->isOnline()) {
                                    if(!$players->hasPermission('vanish.see')) {
                                        $ev = new PlayerTagUpdateEvent($players, new ScoreTag("VanishV2.fake_count", strval(count(self::$online))));
                                        $ev->call();
                                    }else{
                                        $ev = new PlayerTagUpdateEvent($players, new ScoreTag("VanishV2.fake_count", strval(count($this->getServer()->getOnlinePlayers()))));
                                        $ev->call();
                                    }
                                }
                            }
                        }
                        if($this->getConfig()->get("enable-fly") === true) {
                            if($player->getGamemode() === 0) {
                                unset(self::$AllowCombatFly[array_search($name, self::$AllowCombatFly)]); //for BlazinFly compatibility
                                $player->setFlying(false);
                                $player->setAllowFlight(false);
                            }
                        }
                        $pk = new PlayerListPacket();
                        $pk->type = PlayerListPacket::TYPE_ADD;
                        $pk->entries[] = PlayerListEntry::createAdditionEntry(
                            $player->getUniqueId(),
                            $player->getId(),
                            $player->getDisplayName(),
                            SkinAdapterSingleton::get()->toSkinData($player->getSkin()),
                            $player->getXuid());
                        foreach($this->getServer()->getOnlinePlayers() as $p){
                            $p->sendDataPacket($pk);
                        }
                        if($this->getConfig()->get("enable-join") === true) {
                            $msg = $this->getConfig()->get("FakeJoin-message");
                            $msg = str_replace("%name", "$name", $msg);
                            $this->getServer()->broadcastMessage($msg);
                        }
                        $msg = $this->getConfig()->get("unvanish-other");
                        $msg = str_replace("%name", "$name", $msg);
                        $sender->sendMessage(self::PREFIX . $msg);
                        $msg = $this->getConfig()->get("unvanished-other");
                        $msg = str_replace("%other-name", "$othername", $msg);
                        $player->sendMessage(self::PREFIX . $msg);
                    }
                }else{
                    $sender->sendMessage(self::PREFIX . TextFormat::RED . "Player not found");
                }
            }
        }
        return true;
    }
}
