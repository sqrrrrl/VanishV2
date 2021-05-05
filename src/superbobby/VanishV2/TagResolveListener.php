<?php

namespace superbobby\VanishV2;

use pocketmine\event\Listener;
use Ifera\ScoreHud\event\TagsResolveEvent;

class TagResolveListener implements Listener{

    public function onTagResolve(TagsResolveEvent $event){
        $tag = $event->getTag();
        $tags = explode('.', $tag->getName(), 2);
        $value = "";

        if($tags[0] !== 'VanishV2' || count($tags) < 2){
            return;
        }

        switch($tags[1]){
            case "fake_count":
                $value = count(VanishV2::$online);
                break;
        }
        $tag->setValue(strval($value));
    }
}