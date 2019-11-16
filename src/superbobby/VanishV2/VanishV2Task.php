<?php

namespace superbobby\VanishV2;

use pocketmine\scheduler\Task;
use pocketmine\Server;

use function in_array;

class VanishV2Task extends Task {

    public function onRun(int $currentTick){
        foreach(Server::getInstance()->getOnlinePlayers() as $p){
            if($p->spawned){
                if(in_array($p->getName(), VanishV2::$vanish)){
                    foreach(Server::getInstance()->getOnlinePlayers() as $player){
			if($player->hasPermission("vanish.see")){
			  $player->showPlayer($p);
		       }else{
			  $player->hidePlayer($p);
		       }
                    }
                }
            }
        }
    }
}
