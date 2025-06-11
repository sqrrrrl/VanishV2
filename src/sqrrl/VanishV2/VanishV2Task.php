<?php

declare(strict_types=1);

namespace sqrrl\VanishV2;

use pocketmine\scheduler\Task;

class VanishV2Task extends Task {

    public function __construct(private VanishV2 $plugin) {}

    public function onRun(): void{
        $onlinePlayers = $this->plugin->getServer()->getOnlinePlayers();
        foreach($onlinePlayers as $p){
            if (isset(VanishV2::$vanish[$p->getName()])) {
                $p->sendTip($this->plugin->getConfig()->get("hud-message"));
                foreach($onlinePlayers as $player){
                    if ($player->hasPermission("vanish.see")) {
                        $player->showPlayer($p);
                    }else{
                        $player->hidePlayer($p);
                        $player->getNetworkSession()->onPlayerRemoved($p);
                    }
                }
            }
        }
    }
}
