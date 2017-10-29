<?php
namespace robske_110\ServerSelector\event;

use pocketmine\Player;
use pocketmine\event\plugin\PluginEvent;
use robske_110\ServerSelector\ServerSelector;

class ServerSelectorEvent extends PluginEvent{
	/** @var Player */
	private $player;
	/** @var ServerSelector */
	private $plugin;
	
	public static $handlerList = null;
	
	public function __construct(ServerSelector $plugin, Player $player){
		parent::__construct($plugin);
		$this->plugin = $plugin;
		$this->player = $player;
	}
	
	public function getServerSelector(): ServerSelector{
		return $this->plugin;
	}
	
	public function getPlayer(): Player{
		return $this->player;
	}
}