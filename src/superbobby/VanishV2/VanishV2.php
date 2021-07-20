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
    public const PREFIX = TextFormat::BLUE . "VanishV2 " . TextFormat::DARK_GRAY . "» " . TextFormat::RESET;

    public static array $vanish = [];

    public static array $online = [];

    public static array $AllowCombatFly = [];

    public $pk;

    protected static VanishV2 $main;

    public function onEnable() {
        self::$main = $this;
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->getScheduler()->scheduleRepeatingTask(new VanishV2Task(), 20);
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        if (!class_exists(InvMenuHandler::class)) {
            $this->getLogger()->error("InvMenu virion not found download VanishV2 on poggit or download InvMenu with DEVirion (not recommended)");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
        if ($this->getConfig()->get("config-version") < 5 or $this->getConfig()->get("config-version") == null) {
            $this->getLogger()->error("Your configuration file is outdated you have to delete it to get the new config");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
        if (class_exists(InvMenuHandler::class)) {
            if (!InvMenuHandler::isRegistered()) {
                InvMenuHandler::register($this);
            }
        }
    }

    public static function getMain(): self {
        return self::$main;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
        switch (strtolower($command->getName())) {
            case "vanish":
            case "v":
                if (!$sender instanceof Player) {
                    $sender->sendMessage(self::PREFIX . TextFormat::RED . "Use this command InGame.");
                    return false;
                }

                if (!$sender->hasPermission("vanish.use")) {
                    $sender->sendMessage(TextFormat::RED . "You do not have permission to use this command");
                    return false;
                }

                if (count($args) == 1) {
                    if (!$sender->hasPermission("vanish.use.other")) {
                        $sender->sendMessage(self::PREFIX . TextFormat::RED . "You do not have permission to vanish other players");
                        return false;
                    }
                }

                if (count($args) == 0) {
                    if (!in_array($sender->getName(), self::$vanish)) {
                        $this->vanish($sender);
                        $sender->sendMessage(self::PREFIX . $this->getConfig()->get("vanish-message"));
                    }else{
                        $this->unvanish($sender);
                        $sender->sendMessage(self::PREFIX . $this->getConfig()->get("unvanish-message"));
                    }
                }else{
                    if (count($args) == 1) {
                        $player = $this->getServer()->getPlayer($args[0]);
                        if ($player != null) {
                            if (!in_array($player->getName(), self::$vanish)) {
                                $this->vanish($player);
                                $msg_sender = $this->getConfig()->get("vanish-other");
                                $msg_other = $this->getConfig()->get("vanished-other");
                            }else{
                                $this->unvanish($player);
                                $msg_sender = $this->getConfig()->get("unvanish-other");
                                $msg_other = $this->getConfig()->get("unvanished-other");
                            }
                            $msg_other = str_replace("%other-name", $sender->getName(), $msg_other);
                            $msg_sender = str_replace("%name", $player->getName(), $msg_sender);
                            $sender->sendMessage(self::PREFIX . $msg_sender);
                            $player->sendMessage(self::PREFIX . $msg_other);
                        }else{
                            $sender->sendMessage(self::PREFIX . TextFormat::RED . "Player not found");
                        }
                    }
                }
        }
        return true;
    }

    public function vanish(Player $player) {
        self::$vanish[] = $player->getName();
        unset(self::$online[array_search($player, self::$online, True)]);
        $player->setNameTag(TextFormat::GOLD . "[V] " . TextFormat::RESET . $player->getNameTag());
        $this->updateHudPlayerCount();
        if ($this->getConfig()->get("enable-leave")) {
            $msg = $this->getConfig()->get("FakeLeave-message");
            $msg = str_replace("%name", $player->getName(), $msg);
            $this->getServer()->broadcastMessage($msg);
        }
        if ($this->getConfig()->get("enable-fly")) {
            if ($player->getGamemode() == 0) {
                self::$AllowCombatFly[] = $player->getName();
                $player->setFlying(true);
                $player->setAllowFlight(true);
            }
        }
        foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
            if ($onlinePlayer->hasPermission("vanish.see")) {
                $msg = $this->getConfig()->get("vanish");
                $msg = str_replace("%name", $player->getName(), $msg);
                $onlinePlayer->sendMessage($msg);
            }
        }
    }

    public function unvanish(Player $player) {
        unset(self::$vanish[array_search($player->getName(), self::$vanish)]);
        self::$online[] = $player;
        $player->setNameTag(str_replace("[V] ", null, $player->getNameTag()));
        $this->updateHudPlayerCount();
        foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
            $onlinePlayer->showPlayer($player);
            if ($onlinePlayer->hasPermission("vanish.see")) {
                $msg = $this->getConfig()->get("unvanish");
                $msg = str_replace("%name", $player->getName(), $msg);
                $onlinePlayer->sendMessage($msg);
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
        foreach ($this->getServer()->getOnlinePlayers() as $p) {
            $p->sendDataPacket($pk);
        }
        if ($this->getConfig()->get("enable-fly")) {
            if ($player->getGamemode() == 0) {
                unset(self::$AllowCombatFly[array_search($player->getName(), self::$AllowCombatFly)]);
                $player->setFlying(false);
                $player->setAllowFlight(false);
            }
        }
        if ($this->getConfig()->get("enable-join")) {
            $msg = $this->getConfig()->get("FakeJoin-message");
            $msg = str_replace("%name", $player->getName(), $msg);
            $this->getServer()->broadcastMessage($msg);
        }
    }

    public function checkHudVersion(): bool {
        if ($this->getServer()->getPluginManager()->getPlugin('ScoreHud')) {
            $scorehud_version = floatval($this->getServer()->getPluginManager()->getPlugin('ScoreHud')->getDescription()->getVersion());
            if ($scorehud_version >= 6.0) {
                $this->getServer()->getPluginManager()->registerEvents(new TagResolveListener, $this);
                return true;
            }
        }
        return false;
    }

    public function updateHudPlayerCount() {
        if ($this->checkHudVersion()) {
            foreach ($this->getServer()->getOnlinePlayers() as $player) {
                if ($player->isOnline()) {
                    if (!$player->hasPermission('vanish.see')) {
                        $ev = new PlayerTagUpdateEvent($player, new ScoreTag("VanishV2.fake_count", strval(count(self::$online))));
                    }else{
                        $ev = new PlayerTagUpdateEvent($player, new ScoreTag("VanishV2.fake_count", strval(count($this->getServer()->getOnlinePlayers()))));
                    }
                    $ev->call();
                }
            }
        }
    }
}