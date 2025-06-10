<?php

declare(strict_types=1);

namespace sqrrl\VanishV2;

use MohamadRZ4\Placeholder\PlaceholderAPI;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use Ifera\ScoreHud\event\PlayerTagUpdateEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;
use pocketmine\utils\Config;

use function array_keys;
use function strtolower;

class VanishV2 extends PluginBase {
    public const PREFIX = TextFormat::BLUE . "VanishV2 " . TextFormat::DARK_GRAY . "» " . TextFormat::RESET;

    /** @var array<string, true> */
    public static array $vanish = [];
    /** @var array<string, true> */
    public static array $online = [];

    protected function onEnable(): void {
        $this->getScheduler()->scheduleRepeatingTask(new VanishV2Task($this), 20);
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->initConfig();
        $this->checkVirions();
        if ($this->getServer()->getPluginManager()->getPlugin("PlaceholderAPI") !== null) {
            PlaceholderAPI::getInstance()->registerExpansion(new VanishExpansion());
        }
    }

    protected function onDisable(): void {
        if (!$this->getConfig()->get("unvanish-after-restart")) {
            $file = new Config($this->getDataFolder() . "vanished_players.txt", CONFIG::ENUM);
            $players = implode("\n", array_keys(self::$vanish));
            $file->set($players);
            $file->save();
        }
    }

    private function initConfig(): void {
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        if ($this->getConfig()->get("config-version") < 8 || $this->getConfig()->get("config-version") === null) {
            $this->getLogger()->notice("Updating your config...");
            rename($this->getDataFolder() . "config.yml", $this->getDataFolder() . "config.yml.old");
            $this->saveDefaultConfig();
            $this->getConfig()->reload();
            $this->getLogger()->notice("Config updated!");
        }
        if (!$this->getConfig()->get("unvanish-after-restart")) {
            $file = new Config($this->getDataFolder() . "vanished_players.txt", CONFIG::ENUM);
            $players = $file->getAll(true);
            foreach ($players as $name) {
                self::$vanish[$name] = true;
            }
            unlink($this->getDataFolder() . "vanished_players.txt");
        }
    }

    private function checkVirions(): void {
        if (!class_exists(InvMenuHandler::class)) {
            $this->getLogger()->error("InvMenu virion not found download VanishV2 on poggit or download InvMenu with DEVirion (not recommended)");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
        $commandName = strtolower($command->getName());
        if ($commandName !== "vanish" && $commandName !== "v") {
            return false;
        }

        if (count($args) === 0) {
            if (!$sender instanceof Player) {
                $sender->sendMessage(self::PREFIX . TextFormat::RED . "Use this command In-Game");
                return false;
            }
            if (!isset(self::$vanish[$sender->getName()])) {
                $this->vanish($sender);
                $sender->sendMessage(self::PREFIX . $this->getConfig()->get("vanish-message"));
            }else{
                $this->unvanish($sender);
                $sender->sendMessage(self::PREFIX . $this->getConfig()->get("unvanish-message"));
            }
            return true;
        }

        if (count($args) === 1) {
            if (!$sender->hasPermission("vanish.use.other")) {
                $sender->sendMessage(self::PREFIX . TextFormat::RED . "You do not have permission to vanish other players");
                return false;
            }

            $target = $this->getServer()->getPlayerByPrefix($args[0]);
            if (!$target instanceof Player) {
                $sender->sendMessage(self::PREFIX . TextFormat::RED . "Player not found");
                return false;
            }

            $targetName = $target->getName();
            if (!isset(self::$vanish[$targetName])) {
                $this->vanish($target);
                $msgSender = $this->getConfig()->get("vanish-other");
                $msgTarget = $this->getConfig()->get("vanished-other");
            }else{
                $this->unvanish($target);
                $msgSender = $this->getConfig()->get("unvanish-other");
                $msgTarget = $this->getConfig()->get("unvanished-other");
            }

            $msgSender = str_replace("%name", $targetName, $msgSender);
            $msgTarget = str_replace("%other-name", $sender->getName(), $msgTarget);

            $sender->sendMessage(self::PREFIX . $msgSender);
            $target->sendMessage(self::PREFIX . $msgTarget);
            return true;
        }

        return false;
    }

    public function vanish(Player $player) {
        $name = $player->getName();
        self::$vanish[$name] = true;
        unset(self::$online[$name]);
        $player->setNameTag(TextFormat::GOLD . "[V] " . TextFormat::RESET . $player->getNameTag());
        $this->updateHudPlayerCount();
        if ($this->getConfig()->get("enable-leave")) {
            $msg = $this->getConfig()->get("FakeLeave-message");
            $msg = str_replace("%name", $name, $msg);
            $this->getServer()->broadcastMessage($msg);
        }
        if ($this->getConfig()->get("enable-fly")) {
            if ($player->isSurvival()) {
                $player->setFlying(true);
                $player->setAllowFlight(true);
            }
        }
        foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
            if ($onlinePlayer->hasPermission("vanish.see")) {
                $msg = $this->getConfig()->get("vanish");
                $msg = str_replace("%name", $name, $msg);
                $onlinePlayer->sendMessage($msg);
            }
        }
    }

    public function unvanish(Player $player) {
        $name = $player->getName();
        unset(self::$vanish[$name]);
        self::$online[$name] = true;
        $player->setNameTag(str_replace("[V] ", "", $player->getNameTag()));
        $player->setSilent(false);
        $player->getXpManager()->setCanAttractXpOrbs(true);
        $this->updateHudPlayerCount();
        foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
            $onlinePlayer->showPlayer($player);
            $onlinePlayer->getNetworkSession()->onPlayerAdded($player);
            if ($onlinePlayer->hasPermission("vanish.see")) {
                $msg = $this->getConfig()->get("unvanish");
                $msg = str_replace("%name", $name, $msg);
                $onlinePlayer->sendMessage($msg);
            }
        }
        if ($this->getConfig()->get("enable-fly")) {
            if ($player->isSurvival()) {
                $player->setFlying(false);
                $player->setAllowFlight(false);
            }
        }
        if ($this->getConfig()->get("night-vision")){
            $player->getEffects()->remove(VanillaEffects::NIGHT_VISION());
        }
        if ($this->getConfig()->get("enable-join")) {
            $msg = $this->getConfig()->get("FakeJoin-message");
            $msg = str_replace("%name", $name, $msg);
            $this->getServer()->broadcastMessage($msg);
        }
    }

    private function checkHudVersion(): bool {
        if ($this->getServer()->getPluginManager()->getPlugin("ScoreHud")) {
            if (version_compare($this->getServer()->getPluginManager()->getPlugin("ScoreHud")->getDescription()->getVersion(), "6.0.0", ">=")){
                $this->getServer()->getPluginManager()->registerEvents(new TagResolveListener, $this);
                return true;
            }
        }
        return false;
    }

    public function updateHudPlayerCount(): void {
        if (!$this->checkHudVersion()) {
            return;
        }

        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            if (!$player->hasPermission("vanish.see")) {
                $ev = new PlayerTagUpdateEvent($player, new ScoreTag("VanishV2.fake_count", strval(count(self::$online))));
            }else{
                $ev = new PlayerTagUpdateEvent($player, new ScoreTag("VanishV2.fake_count", strval(count($this->getServer()->getOnlinePlayers()))));
            }
            $ev->call();
        }
    }
}
