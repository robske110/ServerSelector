<?php
namespace robske_110\ServerSelector;

use pocketmine\Player;

class SelectorServer{
	/** @var string  */
	private $hostname;
	/** @var int  */
	private $port;
	
	/** @var null|string  */
	private $permgroup;
	
	/**
	 * @param string      $hostname
	 * @param int         $port
	 * @param null|string $permgroup The permgroup (discrete permission) or simply null (for all players)
	 */
	public function __construct(string $hostname, int $port, ?string $permgroup){
		$this->hostname = $hostname;
		$this->port = $port;
		$this->permgroup = $permgroup;
	}
	
	public function getHostname(): string{
		return $this->hostname;
	}
	
	public function getPort(): int{
		return $this->port;
	}
	
	public static function createFromSaveData(array $saveData): SelectorServer{
		return new SelectorServer($saveData[0], $saveData[1], $saveData[2] == "null" ? null : $saveData[2]);
	}
	
	public function getSaveData(): array{
		return [$this->hostname, $this->port, $this->permgroup ?? "null"];
	}
	
	public function canSee(Player $player){
		if($this->permgroup === null){
			return true;
		}
		//TODO
		return false;
	}
	
	public function getID(): string{
		return $this->hostname."@".$this->port;
	}
}