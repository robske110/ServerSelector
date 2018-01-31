<?php
namespace robske_110\ServerSelector\event;

use pocketmine\event\Cancellable;
use pocketmine\Player;
use robske_110\ServerSelector\ServerSelector;

class ServerSelectorSubmitEvent extends ServerSelectorEvent implements Cancellable{
	public static $handlerList = null;
	
	const ACTION_TYPE_REFRESH = 0;
	const ACTION_TYPE_TRANSFER = 1;
	const ACTION_TYPE_OFFLINE_SERVER = 2;
	
	/** @var int */
	private $actionType;
	/** @var null|string */
	private $ip = null;
	/** @var null|int */
	private $port = null;
	
	public function __construct(ServerSelector $plugin, Player $player, int $actionType, ?string $ip = null, ?int $port = null){
		$this->actionType = $actionType;
		$this->ip = $ip;
		$this->port = $port;
		parent::__construct($plugin, $player);
	}
	
	/**
	 * @return int One of the three ACTION_TYPE constants
	 */
	public function getActionType(): int{
		return $this->actionType;
	}
	
	/**
	 * Returns null on ACTION_TYPE_REFRESH.
	 *
	 * @return null|string
	 */
	public function getIP(): ?string{
		return $this->ip;
	}
	
	/**
	 * Returns null on ACTION_TYPE_REFRESH.
	 *
	 * @return null|int
	 */
	public function getPort(): ?int{
		return $this->port;
	}
}