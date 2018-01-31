<?php
namespace robske_110\ServerSelector\event;

use pocketmine\event\Cancellable;
use pocketmine\Player;
use robske_110\ServerSelector\ServerSelector;

class ServerSelectorOpenEvent extends ServerSelectorEvent implements Cancellable{
	public static $handlerList = null;
	
	/** @var bool */
	private $forced;
	
	public function __construct(ServerSelector $plugin, Player $player, bool $forced){
		$this->forced = $forced;
		parent::__construct($plugin, $player);
	}
	
	/**
	 * @return bool
	 */
	public function isForced(): bool{
		return $this->forced;
	}
}