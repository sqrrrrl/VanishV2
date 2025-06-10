<?php

declare(strict_types=1);

namespace sqrrl\VanishV2;

use pocketmine\block\Chest;
use pocketmine\block\inventory\DoubleChestInventory;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\tile\Chest as TileChest;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityCombustEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\QueryRegenerateEvent;
use pocketmine\event\world\WorldSoundEvent;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\ClosureTask;
use muqsit\invmenu\InvMenu;

use function array_keys;

class EventListener implements Listener {

    /** @var array<string, true> */
    private static array $silentBlocks = [];

    public function __construct(private VanishV2 $plugin) {}

    public function onQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        $name = $player->getName();
        if (isset(VanishV2::$vanish[$name])) {
            if ($this->plugin->getConfig()->get("unvanish-after-leaving")) {
                unset(VanishV2::$vanish[$name]);
            }
        }
        if (isset(VanishV2::$online[$name])){
            unset(VanishV2::$online[$name]);
            $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function(): void{
                $this->plugin->updateHudPlayerCount();
            }), 20);
        }
    }

    public function pickUp(EntityItemPickupEvent $event) {
        $entity = $event->getEntity();
        if ($entity instanceof Player) {
            if (isset(VanishV2::$vanish[$entity->getName()])) {
                $event->cancel();
            }
        }
    }

    public function onDamage(EntityDamageEvent $event) {
        $player = $event->getEntity();
        if ($player instanceof Player) {
            if (isset(VanishV2::$vanish[$player->getName()])) {
                if ($this->plugin->getConfig()->get("disable-damage")) {
                    $event->cancel();
                }
            }
        }
    }

    public function onPlayerBurn(EntityCombustEvent $event) {
        $player = $event->getEntity();
        if ($player instanceof Player) {
            if (isset(VanishV2::$vanish[$player->getName()])) {
                if ($this->plugin->getConfig()->get("disable-damage")) {
                    $event->cancel();
                }
            }
        }
    }

    public function onExhaust(PlayerExhaustEvent $event) {
        $player = $event->getPlayer();
        if (isset(VanishV2::$vanish[$player->getName()])) {
            if (!$this->plugin->getConfig()->get("hunger")){
                $event->cancel();
            }
        }
    }

    public function onJoin(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        $name = $player->getName();
        if (!isset(VanishV2::$vanish[$name]) && !isset(VanishV2::$online[$name])) {
            VanishV2::$online[$name] = true;
            $this->plugin->updateHudPlayerCount();
        }
    }

    /**
     * @param PlayerJoinEvent $event
     * @priority HIGHEST
     */
    public function setNametag(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        if (isset(VanishV2::$vanish[$player->getName()])){
            $player->setNameTag(TextFormat::GOLD . "[V] " . TextFormat::RESET . $player->getNameTag());
        }
    }

    public function onQuery(QueryRegenerateEvent $event) {
        $event->getQueryInfo()->setPlayerList(array_keys(VanishV2::$online));
        foreach(Server::getInstance()->getOnlinePlayers() as $p) {
            if (isset(VanishV2::$vanish[$p->getName()])) {
                $online = $event->getQueryInfo()->getPlayerCount();
                $event->getQueryInfo()->setPlayerCount($online - 1);
            }
        }
    }

    public function onInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();

        if (
            !isset(VanishV2::$vanish[$player->getName()]) ||
            !$this->plugin->getConfig()->get("silent-chest") ||
            !$block instanceof Chest ||
            $player->isSneaking()
        ) {
            return;
        }

        if ($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            return;
        }

        $event->cancel();

        $tile = $block->getPosition()->getWorld()->getTile($block->getPosition());
        if (!$tile instanceof TileChest) {
            return;
        }

        $inventory = $tile->getInventory();
        $contents = $inventory->getContents();

        if (empty($contents)) {
            $player->sendMessage(VanishV2::PREFIX . TextFormat::RED . "This chest is empty");
            return;
        }

        $menu = InvMenu::create($inventory instanceof DoubleChestInventory ? InvMenu::TYPE_DOUBLE_CHEST : InvMenu::TYPE_CHEST);
        $menu->getInventory()->setContents($contents);
        $menu->setListener(InvMenu::readonly());
        $menu->setName($block->getName());
        $menu->send($player);
    }

    /**
     * @param PlayerJoinEvent $event
     * @priority HIGHEST
     */
    public function silentJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        if (!$player->hasPermission("vanish.silent")) {
            return;
        }

        $config = $this->plugin->getConfig()->get("silent-join-leave");
        if (!$config["join"]) {
          return;
        }

        if (!$config["vanished-only"] || isset(VanishV2::$vanish[$player->getName()])) {
            $event->setJoinMessage("");
        }
    }

    /**
     * @param PlayerQuitEvent $event
     * @priority HIGHEST
     */
    public function silentLeave(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        if (!$player->hasPermission("vanish.silent")) {
            return;
        }

        $config = $this->plugin->getConfig()->get("silent-join-leave");
        if (!$config["leave"]) {
            return;
        }

        if (!$config["vanished-only"] || isset(VanishV2::$vanish[$player->getName()])) {
            $event->setQuitMessage("");
        }
    }

    public function onCommandExecute(CommandEvent $event){
        $sender = $event->getSender();
        if (!$sender instanceof Player || $this->plugin->getConfig()->get("can-send-msg")) {
            return;
        }

        $args = explode(" ", $event->getCommand());
        $command = strtolower(array_shift($args));
        $receiverName = array_shift($args);
        $message = implode(" ", $args);

        if (!$receiverName || $message === "") {
            return;
        }

        $receiver = $this->plugin->getServer()->getPlayerByPrefix($receiverName);
        if (
            !$receiver ||
            $sender === $receiver ||
            $sender->hasPermission("vanish.see") ||
            !isset(VanishV2::$vanish[$receiver->getName()])
        ) {
            return;
        }

        $messagingCommands = array_fill_keys(["tell", "msg", "w"], true);

        $additionalCommands = $this->plugin->getConfig()->get("additional-commands");

        if (isset($messagingCommands[$command])) {
            $event->cancel();
            $sender->sendMessage($this->plugin->getConfig()->get("messages")["sender-error"]);
            $receiver->sendMessage(VanishV2::PREFIX . str_replace(
                ["%sender", "%message"],
                [$sender->getName(), $message],
                $this->plugin->getConfig()->get("messages")["receiver-message"]
            ));
        } elseif (is_array($additionalCommands) && isset($additionalCommands[$command])) {
            $event->cancel();
            $sender->sendMessage($additionalCommands[$command]["sender-error"]);
            $receiver->sendMessage(VanishV2::PREFIX . str_replace(
                "%sender",
                $sender->getName(),
                $additionalCommands[$command]["receiver-message"]
            ));
        }
    }

    public function onAttack(EntityDamageByEntityEvent $event){
        $damager = $event->getDamager();
        $player = $event->getEntity();
        if ($damager instanceof Player && $player instanceof Player){
            if (!$damager->hasPermission("vanish.attack")){
                if (isset(VanishV2::$vanish[$damager->getName()])){
                    $damager->sendMessage($this->plugin->getConfig()->get("hit-no-permission"));
                    $event->cancel();
                }
            }
        }
    }

    /**
     * @param PlayerInteractEvent $event
     * @priority LOWEST
     */
    public function onBlockInteract(PlayerInteractEvent $event){
        $player = $event->getPlayer();
        if (!isset(VanishV2::$vanish[$player->getName()]) && $event->getAction() !== PlayerInteractEvent::LEFT_CLICK_BLOCK) {
            return;
        }

        $block = $event->getBlock();
        $position = $block->getPosition();
        $delay = round($block->getBreakInfo()->getBreakTime($player->getInventory()->getItemInHand())) * 20;
        self::$silentBlocks[(string) $position] = true;
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($position): void{
            unset(self::$silentBlocks[(string) $position]);
        }), (int) $delay);
    }

    /**
     * @param WorldSoundEvent $event
     * @priority HIGHEST
     */
    public function onWorldSoundBroadcast(WorldSoundEvent $event){
        $position = $event->getPosition();
        if (isset(self::$silentBlocks[(string) $position])) {
            $event->cancel();
        }
    }

    /**
     * @param BlockBreakEvent $event
     * @priority HIGHEST
     */
    public function onBlockBreak(BlockBreakEvent $event){
        if ($event->isCancelled()) {
            return;
        }

        $player = $event->getPlayer();
        $inventory = $player->getInventory();
        $world = $player->getWorld();
        $block = $event->getBlock();
        $position = $block->getPosition();

        if (!isset(VanishV2::$vanish[$player->getName()])) {
            return;
        }

        $event->cancel();
        $world->setBlock($position, VanillaBlocks::AIR());

        if (!$player->isSurvival()) {
            return;
        }

        $drops = $event->getDrops();
        $xpDrop = $event->getXpDropAmount();
        $player->getXpManager()->addXp($xpDrop);

        foreach ($drops as $drop) {
            if ($player->getInventory()->canAddItem($drop)) {
                $player->getInventory()->addItem($drop);
            }else{
                $world->dropItem($position->add(0.5, 0.5, 0.5), $drop);
            }
        }

        $item = $inventory->getItemInHand();
        $returnedItems = [];
        $item->onDestroyBlock($block, $returnedItems);
        $inventory->setItemInHand($item);
    }

    /**
     * @param BlockPlaceEvent $event
     * @priority HIGHEST
     */
    public function onBlockPlace(BlockPlaceEvent $event){
        if ($event->isCancelled()){
            return;
        }
        $player = $event->getPlayer();
        if (isset(VanishV2::$vanish[$player->getName()])){
            $event->cancel();
            $event->getTransaction()->apply();
            if ($player->isSurvival()){
                $player->getInventory()->removeItem($event->getItem()->pop());
            }
        }
    }
}
