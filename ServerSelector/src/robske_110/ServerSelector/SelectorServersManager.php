<?php
namespace robske_110\ServerSelector;

class SelectorServersManager{
	/** @var ServerSelector */
	private $plugin;
	/** @var array */
	private $servers = []; //[string hostname, int port, ?bool online, ?array playerCount, ?string modt]
	/** @var int */
	private $dataRefreshTick = -1;
	
	public function __construct(ServerSelector $plugin){
		$this->plugin = $plugin;
	}
	
	/**
	 * Adds a server to the internal ServerList, but does not check if it is already registered to SSS neither save it to disk or the real list.
	 * NOTE: This function is probably not what you want! The server will not show up on the list!
	 *
	 * @param string $hostname
	 * @param int    $port
	 *
	 * @return bool
	 */
	public function addServer(string $hostname, int $port): bool{
		if(!isset($this->servers[$hostname."@".$port])){
			$this->servers[$hostname."@".$port] = [$hostname, $port, null];
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * WARNING: BASICALLY NEVER USE THIS FUNCTION UNLESS YOU ABSOLUTELY KNOW WHAT YOU ARE DOING!
	 * Adds a server to the internal ServerList, but does not check if it has been registered to SSS neither remove it from disk or the real list.
	 * NOTE: This function is probably not what you want! This will put a server on the list into an unknown state.
	 *
	 * @param string $hostname
	 * @param int    $port
	 *
	 * @return bool
	 */
	public function remServer(string $hostname, int $port): bool{
		if(isset($this->servers[$hostname."@".$port])){
			unset($this->servers[$hostname."@".$port]);
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * Gets the Servers on the ServerSelector and performs an update, if available, on them beforehand.
	 *
	 * @return array
	 */
	public function getServers(): array{
		if(!$this->update()){
			$this->plugin->getLogger()->critical("Unexpected error: Trying to get SignServerStats plugin instance failed!");
			$this->plugin->getServer()->getPluginManager()->disablePlugin($this->plugin);
		}
		return $this->servers;
	}
	
	/**
	 * Returns the Tick in which the data for the servers were started to be generated.
	 *
	 * @return int
	 */
	public function getStatusServerRefreshTick(): int{
		return $this->dataRefreshTick;
	}
	
	public function update(): bool{
		if(($sss = $this->plugin->getSSS()) === null){
			return false;
		}
		if(($lastRefreshTick = $sss->getLastRefreshTick()) < $this->dataRefreshTick){
			return true;
		}
		$serverOnlineArray = $sss->getServerOnline();
		$playerOnlineArray = $sss->getPlayerData();
		$serverModtArray = $sss->getMODTs();
		foreach($this->servers as $index => $listServer){
			if(isset($serverOnlineArray[$index])){
		    	$this->servers[$index][2] = $serverOnlineArray[$index];
				if(isset($playerOnlineArray[$index])){
					$this->servers[$index][3] = $playerOnlineArray[$index];
				}else{
					$this->servers[$index][3] = null;
				}
				if(isset($serverModtArray[$index])){
					$this->servers[$index][4] = $serverModtArray[$index];
				}else{
					$this->servers[$index][4] = null;
				}
			}else{
				$this->servers[$index][2] = null;
				$this->servers[$index][3] = null;
				$this->servers[$index][4] = null;
			}
		}
		$this->dataRefreshTick = $lastRefreshTick;
		return true;
	}
}