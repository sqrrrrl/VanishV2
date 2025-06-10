<?php

namespace sqrrl\VanishV2;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\scheduler\Task;
use pocketmine\Server;

class VanishV2Task extends Task {

    public function __construct(private VanishV2 $plugin) {}

    public function onRun(): void{
        foreach(Server::getInstance()->getOnlinePlayers() as $p){
            if (isset(VanishV2::$vanish[$p->getName()])) {
                $p->sendTip($this->plugin->getConfig()->get("hud-message"));
                $p->getXpManager()->setCanAttractXpOrbs(false);
                if ($this->plugin->getConfig()->get("night-vision")) {
                    $p->getEffects()->add(new EffectInstance(VanillaEffects::NIGHT_VISION(), null, 0, false));
                }
                foreach(Server::getInstance()->getOnlinePlayers() as $player){
                    if ($player->hasPermission("vanish.see")) {
                        $player->showPlayer($p);
                    }else{
                        $player->hidePlayer($p);
                        $player->getNetworkSession()->onPlayerRemoved($p)
                    }
                }
            }
        }
    }
}
