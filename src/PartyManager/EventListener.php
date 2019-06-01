<?php
namespace PartyManager;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use pocketmine\Server;

class EventListener implements Listener {
    private $plugin;

    public function __construct(PartyManager $plugin) {
        $this->plugin = $plugin;
    }

    /*public function onQuit(PlayerQuitEvent $ev){
      if(!isset($this->plugin->mdata[$ev->getPlayer()->getName()])) return;
      if($this->plugin->mdata[$ev->getPlayer()->getName()]["직위"] == "파티장"){
        foreach($this->plugin->pdata[$this->plugin->getParty($ev->getPlayer()->getName())]["파티원"] as $members){
          if(Server::getInstance()->getPlayer($members) instanceof Player){
            Server::getInstance()->getPlayer($members)->sendMessage("{$this->plugin->pre} 파티장이 로그아웃하여 파티가 해체되었습니다.");
          }
        }
        $this->plugin->removeParty($this->plugin->mdata[$ev->getPlayer()->getName()]["파티"]);
        return;
      }
    }*/
}
