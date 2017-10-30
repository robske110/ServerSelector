<?php
/*          _         _                  __ __  ___  
           | |       | |                /_ /_ |/ _ \ 
  _ __ ___ | |__  ___| | _____           | || | | | |
 | '__/ _ \| '_ \/ __| |/ / _ \          | || | | | |
 | | | (_) | |_) \__ \   <  __/  ______  | || | |_| |
 |_|  \___/|_.__/|___/_|\_\___| |______| |_||_|\___/                      
*/
namespace robske_110\ServerSelector;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;

use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use pocketmine\Server;

use robske_110\ServerSelector\event\ServerSelectorOpenEvent;
use robske_110\SSS\SignServerStats;

class ServerSelector extends PluginBase{
	/** @var Config */
	private $serverSelectorCfg;
	
	/** @var Config */
	private $db;
	/** @var SelectorServer[] */
	private $servers = [];
	/** @var array */
	private $ownedServers = [];

    /** @var ServerSelectorListener */
	private $listener;
	/** @var SelectorServersManager */
	private $selectorServersManager;
	/** @var SelectorRenderer */
	private $selectorRenderer;

	/** @var Server */
	private $server;
	
	const API_VERSION = "0.1.0-InDev";
	const SSS_API_VERSION = "1.0.0";
	
	const DEFAULT_STYLES = [
		"selector.unknown.playercount" => "%WHITE%-/-",
		"selector.known.playercount" => "%WHITE%[%DARK_GREEN%%currPlayers%%WHITE%/%BLUE%%maxPlayers%%WHITE%]",
		"selector.online.line1" => "%ip%%WHITE%:%BLACK%%port%",
		"selector.online.line2" => "%modt% %playercount%",
		"selector.offline.line1" => "%ip%%WHITE%:%BLACK%%port%",
		"selector.offline.line2" => "%DARK_RED%Offline %playercount%",
		"selector.loading.line1" => "%ip%%WHITE%:%BLACK%%port%",
		"selector.loading.line2" => "%GOLD%Loading... %WHITE%%playercount%",
		"selector.unknown.error" => "%RED%An error occurred while processing the Server:",
		"selector.refresh-button" => "%GREEN%Refresh\nLast refresh %DARK_GRAY%%lastrefresh%%GREEN%s ago",
		"variableSeparator" => "%"
	];
	
	public function onEnable(){
		if(($sss = $this->getSSS()) !== null){
			if(!$sss->isCompatible(self::SSS_API_VERSION)){
				$newOld = version_compare(self::SSS_API_VERSION, SignServerStats::API_VERSION, ">") ? "old" : "new";
				$this->getLogger()->critical("Your version of SignServerStats is too ".$newOld." for this plugin.");
				$this->getServer()->getPluginManager()->disablePlugin($this);
				return;
			}
		}else{
			$this->getLogger()->critical("SignServerStats is required for this plugin. PM ignored my dependencies!");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		
		$this->server = $this->getServer();
		
		@mkdir($this->getDataFolder());
		$this->serverSelectorCfg = new Config($this->getDataFolder()."config.yml", Config::YAML, []);
		if($this->serverSelectorCfg->get("ConfigVersion") != 0.01){
			$this->serverSelectorCfg->set('selector-open-items', [Item::COMPASS]);
			$this->serverSelectorCfg->set('selector-item-open-levels', null);
			/*$this->serverSelectorCfg->set('selector-item-status-display', true);
			$this->serverSelectorCfg->set('selector-item-online', );
			$this->serverSelectorCfg->set('selector-item-offline', );*/
			$this->serverSelectorCfg->set('hide-unknown', false);
			$this->serverSelectorCfg->set('ConfigVersion', 0.1);
		}
		$this->serverSelectorCfg->save();
		
		$this->selectorRenderer = new SelectorRenderer($this, new Config($this->getDataFolder()."styles.yml", Config::YAML, self::DEFAULT_STYLES));
		$this->selectorRenderer->setHideUnknown((bool) $this->serverSelectorCfg->get('hide-unknown'));
		
		$this->selectorServersManager = new SelectorServersManager($this);
		$this->db = new Config($this->getDataFolder()."ServerSelectorDB.yml", Config::YAML, []); //TODO:betterDB
		foreach($this->db->getAll() as $savedServer){
			$server = SelectorServer::createFromSaveData($savedServer);
			if(!$this->addServer($server)){
				throw new \Exception("Failure while registering server ".$server->getID().": Server already registered");
			}
		}
		
		$this->listener = new ServerSelectorListener($this, [$this->serverSelectorCfg->get('selector-open-items'), $this->serverSelectorCfg->get('selector-item-open-levels')]);
		$this->server->getPluginManager()->registerEvents($this->listener, $this);
	}
	
	/**
	 * @return SelectorRenderer
	 */
	public function getSelectorRenderer(): SelectorRenderer{
		return $this->selectorRenderer;
	}
	
	/**
	 * @param Player $player
	 * @param bool   $force Ignores player permission and event cancellation
	 */
	public function openSelector(Player $player, bool $force = false){
		if($force || $player->hasPermission("ServerSelector.openList")){
			$event = new ServerSelectorOpenEvent($this, $player); //TODO: isForced
			$this->server->getPluginManager()->callEvent($event);
			if($force || !$event->isCancelled()){
				$this->selectorRenderer->render($player);
			}
		}
	}
	
	/**
	 * This is for extension plugins to test if they are compatible with the version
	 * of ServerSelector installed. Extending plugins should be disabled/disable any
	 * interfacing with this plugin if this returns false.
	 *
	 * @param string $apiVersion The API version your plugin was last tested on.
	 *
	 * @return bool Indicates whether your plugin is compatible.
	 */
	public function isCompatible(string $apiVersion): bool{
		$extensionApiVersion = explode(".", $apiVersion);
		$myApiVersion = explode(".", self::API_VERSION);
		if($extensionApiVersion[0] !== $myApiVersion[0]){
			return false;
		}
		if($extensionApiVersion[1] > $myApiVersion[1]){
			return false;
		}
		return true;
	}
	
	/**
	 * @return SelectorServer[]
	 */
	public function getServers(): array{
		return $this->servers;
	}
	
	/**
	 * @return null|SignServerStats
	 */
	public function getSSS(): ?SignServerStats{
		if(($sss = $this->getServer()->getPluginManager()->getPlugin("SignServerStats")) instanceof SignServerStats){
			return $sss;
		}else{
			$this->getLogger()->critical("Unexpected error: Trying to get SignServerStats plugin instance failed!");
			return null;
		}
	}
	
	/**
	 * @return SelectorServersManager
	 */
	public function getSelectorServersManager(): SelectorServersManager{
		return $this->selectorServersManager;
	}
	
	/**
	 * @param SelectorServer $server
	 * @param bool $save
	 * @param null|SignServerStats $sss
	 *
	 * @return bool
	 */
	public function addServer(SelectorServer $server, bool $save = true, ?SignServerStats $sss = null): bool{
		if($sss === null){
			$sss = $this->getSSS();
		}
		if($this->selectorServersManager->addServer($server->getHostname(), $server->getPort())){
			$this->servers[$server->getID()] = $server;
			if($sss->addServer($server->getHostname(), $server->getPort())){
				$this->ownedServers[$server->getID()] = null;
			}
			if($save){
				$this->db->set($server->getID(), $server->getSaveData());
				$this->db->save(true);
			}
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * @param SelectorServer $server
	 * @param bool $save
	 * @param null|SignServerStats $sss
	 *
	 * @return bool
	 */
	public function remServer(SelectorServer $server, bool $save = true, ?SignServerStats $sss = null): bool{
		if($this->selectorServersManager->remServer($server->getHostname(), $server->getPort())){
			unset($this->servers[$server->getID()]);
			if(array_key_exists($server->getID(), $this->ownedServers)){
				$sss->removeServer($server->getHostname(), $server->getPort());
				unset($this->ownedServers[$server->getID()]);
			}
			if($save){
				$listServers = $this->db->getAll();
				unset($listServers[$server->getID()]);
				$this->db->setAll($listServers);
				$this->db->save(true);
			}
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * Adds a Server to the ServerSelector
	 *
	 * @param string		       $hostname
	 * @param int			       $port
	 * @param null|string          $permGroup The permgroup (discrete permission) or simply null (for all players)
	 * @param bool			       $save Whether the server should be saved to disk and reloaded on next reboot or not.
	 * @param null|SignServerStats $sss
	 *
	 * @return bool
	 */
	public function addSelectorServer(string $hostname, int $port, ?string $permGroup = null, bool $save = true, ?SignServerStats $sss = null): bool{
		$server = new SelectorServer($hostname, $port, $permGroup);
		return $this->addServer($server, $save, $sss);
	}
	
	/**
	 * Removes a Server from the ServerSelector
	 *
	 * @param string		  $hostname
	 * @param int			  $port
	 * @param bool			  $save Whether the removal should be saved to disk and therfore whether the server
	 *                        should also be gone on next reboot or not.
	 * @param SignServerStats $sss
	 *
	 * @return bool
	 */
	public function remSelectorServer(string $hostname, int $port, bool $save = true, SignServerStats $sss): bool{
		if(isset($this->servers[$hostname."@".$port])){
			$server = $this->servers[$hostname."@".$port];
			return $this->remServer($server, $save, $sss);
		}else{
			return false;
		}
	}
	
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
		switch($command->getName()){
			case "serverselector add":
			case "serverselector rem":
				if(isset($args[0])){
					$hostname = $args[0];
					$port = 19132;
					if(isset($args[1])){
						if(is_numeric($args[1])){
							$port = $args[1];
						}else{
							return false;
						}
					}
				}else{
					return false;
				}
				if(($sss = $this->getSSS()) === null){
					$sender->sendMessage("Error. Check console.");
					return true;
				}
			break;
			case "serverselector show":
				if($sender instanceof Player){
					$this->openSelector($sender);
				}
				return true;
			break;
		}
		switch($command->getName()){
			case "serverselector add":
				if($this->addSelectorServer($hostname, $port, null, true, $sss)){
					$sender->sendMessage(
						TF::GREEN."Successfully added the server ".
						TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::GREEN.
						" to the ServerSelector."
					);
				}else{
					$sender->sendMessage(
						TF::DARK_RED."The server ".
						TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::DARK_RED.
						" is already on the ServerSelector!"
					);
				}
				return true;
			break;
			case "serverselector rem":
				if($this->remSelectorServer($hostname, $port, true, $sss)){
					$sender->sendMessage(
						TF::GREEN."Successfully removed the server ".
						TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::GREEN.
						" from the serverselector."
					);
				}else{
					$sender->sendMessage(
						TF::DARK_RED."The server ".
						TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::DARK_RED.
						" is not on the ServerSelector!"
					);
				}
				return true;
			break;
		}
		return false;
	}
}