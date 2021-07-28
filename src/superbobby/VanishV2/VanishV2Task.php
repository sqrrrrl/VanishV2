<?php

namespace superbobby\VanishV2;

use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\scheduler\Task;
use pocketmine\Server;

use function in_array;

class VanishV2Task extends Task {
    public $pk;

    private VanishV2 $plugin;

    public function __construct(VanishV2 $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(int $currentTick){
        foreach(Server::getInstance()->getOnlinePlayers() as $p){
            if($p->spawned){
                if(in_array($p->getName(), VanishV2::$vanish)){
                    foreach(Server::getInstance()->getOnlinePlayers() as $player){
                        $p->sendTip($this->plugin->getConfig()->get("hud-message"));
                        if ($this->plugin->getConfig()->get("night-vision")) {
                            $p->addEffect(new EffectInstance(Effect::getEffect(Effect::NIGHT_VISION), null, 0, false, true));
                        }
			            if($player->hasPermission("vanish.see")){
			                $player->showPlayer($p);
		                }else{
			                $player->hidePlayer($p);
			                $entry = new PlayerListEntry();
			                $entry->uuid = $p->getUniqueId();

			                $pk = new PlayerListPacket();
			                $pk->entries[] = $entry;
			                $pk->type = PlayerListPacket::TYPE_REMOVE;
			                $player->sendDataPacket($pk);
		                }
                    }
                }
            }
        }
    }
}