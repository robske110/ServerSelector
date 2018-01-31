<?php
namespace robske_110\ServerSelector;

use pocketmine\Player;
use pocketmine\Server;

class SelectorServer{
	/** @var string  */
	private $hostname;
	/** @var int  */
	private $port;
	
	/** @var null|string  */
	private $displayName;
	/** @var null|string  */
	private $permGroup = null;
	
	private const SD_HOSTNAME = 0;
	private const SD_PORT = 1;
	private const SD_PERMGROUP = 2;
	private const SD_DISPLAYNAME = 3;
	
	/**
	 * @param string      $hostname
	 * @param int         $port
	 * @param null|string $displayName The displayname which is available as a special var for styles.
	 * @param null|string $permGroup $permGroup The permgroup (discrete permission) or simply null (for all players)
	 */
	public function __construct(string $hostname, int $port, ?string $displayName = null, ?string $permGroup){
		$this->hostname = $hostname;
		$this->port = $port;
		$this->displayName = $displayName;
		$this->setPermGroup($permGroup);
	}

	/**
	 * @param array $saveData The array that has been returned by SelectorServer->getSaveData()
	 *
	 * @return SelectorServer
	 */
	public static function createFromSaveData(array $saveData): ?SelectorServer{
		$selectorServer = new SelectorServer(
			$saveData[self::SD_HOSTNAME], $saveData[self::SD_PORT], $saveData[self::SD_DISPLAYNAME]
		);
		if(!$selectorServer->setPermGroup($saveData[self::SD_PERMGROUP])){
			return null;
		}
		return $selectorServer;
	}

	public function getHostname(): string{
		return $this->hostname;
	}
	
	public function getPort(): int{
		return $this->port;
	}

	public function getID(): string{
		return $this->hostname."@".$this->port;
	}
	
	public function getDisplayName(): ?string{
		return $this->displayName;
	}

	public function setDisplayName(?string $displayName){
		$this->displayName = $displayName;
	}

	public function canSee(Player $player): bool{
		if($this->permGroup === null){
			return true;
		}
		if($player->hasPermission($this->permGroup)){
			return true;
		}
		return false;
	}
	
	public function setPermGroup(?string $permGroup): bool{
		if($permGroup !== null){
			$addPerm = false;
			if(strpos("ServerSelector.viewServer", $this->permGroup) !== null){
				if($permGroup !== "ServerSelector.viewServer.".$this->getID()){
					return false;
				}
				$addPerm = true;
			}
			if(!isset(Server::getInstance()->getPluginManager()->getPermissions()[$permGroup])){
				if($addPerm){
					Server::getInstance()->getPluginManager()->getPermission("ServerSelector.viewServer")->getChildren()[$permGroup] = false;
				}else{
					return false;
				}
			}
		}
		$this->permGroup = $permGroup;
		return true;
	}

	public function getSaveData(): array{
		return [$this->hostname, $this->port, $this->displayName, $this->permGroup];
	}
}