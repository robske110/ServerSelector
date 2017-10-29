<?php
namespace robske_110\ServerSelector;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
#use robske_110\SSS\event\SSSasyncUpdateEvent;

class ServerSelectorListener implements Listener{
	private $main;
	private $server;
	private $openItemOptions;
	
	public function __construct(ServerSelector $main, array $openItemOptions){
		$this->openItemOptions = $openItemOptions;
		$this->main = $main;
		$this->server = $main->getServer();
	}
	
	public function onItemUse(PlayerInteractEvent $event){
		if($event->getAction() !== PlayerInteractEvent::PHYSICAL){
			if(in_array($event->getItem()->getId(), $this->openItemOptions[0])){
				if($this->openItemOptions[1][0] == null || in_array($event->getPlayer()->getLevel()->getName(), $this->openItemOptions[1])){
					$this->main->openSelector($event->getPlayer());
				}
			}
		}
	}
}
//Theory is when you know something, but it doesn"t work. Practice is when something works, but you don"t know why. Programmers combine theory and practice: Nothing works and they don"t know why!