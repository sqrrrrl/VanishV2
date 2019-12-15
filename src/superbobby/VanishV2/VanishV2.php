<?php

namespace superbobby\VanishV2;

use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\PluginTask;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\SkinData;
use pocketmine\network\mcpe\protocol\types\SkinAdapterSingleton;

use function array_search;
use function in_array;
use function strtolower;
use function sendFullPlayerListData;

class VanishV2 extends PluginBase {
    public const PREFIX = "§9Vanish §8» §r";

    public static $vanish = [];

    public $pk;

    protected static $main;

    public function onEnable(){
        self::$main = $this;
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
        $this->getScheduler()->scheduleRepeatingTask(new VanishV2Task(), 20);
    }

    public static function getMain(): self{
        return self::$main;
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool{
        $name = $sender->getName();
        if(strtolower($cmd->getName()) === "vanish" or ($cmd->getName()) === "v"){
	    if(!$sender instanceof Player){
                $sender->sendMessage(self::PREFIX."§4Use this command InGame");
                return false;
            }
	    if(!$sender->hasPermission("vanish.use")){
		$sender->sendMessage(self::PREFIX."§4You do not have permission to use this command");
                return false;
            }
            if(!in_array($name, self::$vanish)){
                self::$vanish[] = $name;
		$sender->sendMessage(self::PREFIX."§aYou are now vanished.");
		$name = $sender->getName();
		$sender->setNameTag("§6[VANISH]§r $name");
		$entry = new PlayerListEntry();
		$entry->uuid = $sender->getUniqueId();

		$pk = new PlayerListPacket();
		$pk->entries[] = $entry;
		$pk->type = PlayerListPacket::TYPE_REMOVE;

		foreach($this->getServer()->getOnlinePlayers() as $players);
	            $players->sendDataPacket($pk);
            }else{
                unset(self::$vanish[array_search($name, self::$vanish)]);
                foreach($this->getServer()->getOnlinePlayers() as $players){
                    $players->showPlayer($sender);
                }
        $pk = new PlayerListPacket();
        $pk->type = PlayerListPacket::TYPE_ADD;
        foreach($this->getServer()->getOnlinePlayers() as $p){
            $pk->entries[] = PlayerListEntry::createAdditionEntry($sender->getUniqueId(), $sender->getId(), $sender->getDisplayName(), SkinAdapterSingleton::get()->toSkinData($sender->getSkin()), $sender->getXuid());
        }

        $p->dataPacket($pk);
                $sender->sendMessage(self::PREFIX."§4You are no longer vanished!");
                $name = $sender->getName();
                $sender->setNameTag("$name");
                }
            }
        return true;
       }
    }
