<?php

namespace sqrrl\VanishV2;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\scheduler\Task;
use pocketmine\Server;

use function in_array;

class VanishV2Task extends Task {
    private VanishV2 $plugin;

    public function __construct(VanishV2 $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void{
        foreach(Server::getInstance()->getOnlinePlayers() as $p){
            if($p->spawned){
                if(in_array($p->getName(), VanishV2::$vanish)){
                    $p->sendTip($this->plugin->getConfig()->get("hud-message"));
                    $p->setSilent(true);
                    $p->getXpManager()->setCanAttractXpOrbs(false);
                    if ($this->plugin->getConfig()->get("night-vision")) {
                        $p->getEffects()->add(new EffectInstance(VanillaEffects::NIGHT_VISION(), null, 0, false));
                    }
                    foreach(Server::getInstance()->getOnlinePlayers() as $player){
                        if($player->hasPermission("vanish.see")){
                            $player->showPlayer($p);
                        }else{
                            $player->hidePlayer($p);
                            $player->getNetworkSession()->sendDataPacket(PlayerListPacket::remove([PlayerListEntry::createRemovalEntry($p->getUniqueId())]));
                        }
                    }
                }
            }
        }
    }
}
