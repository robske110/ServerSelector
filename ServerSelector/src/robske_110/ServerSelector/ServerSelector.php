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
	
	const API_VERSION = "0.2.0";
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
		"selector.title" => "ServerSelector",
		"selector.introduction-text" => "Select the server you want to switch to:",
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
			/** @TODO */
			/*$this->serverSelectorCfg->set('selector-item-status-display', true);
			$this->serverSelectorCfg->set('selector-item-online', "");
			$this->serverSelectorCfg->set('selector-item-offline', "");*/
			$this->serverSelectorCfg->set('hide-offline', false);
			$this->serverSelectorCfg->set('hide-unknown', false);
			$this->serverSelectorCfg->set('ConfigVersion', 0.2);
		}
		$this->serverSelectorCfg->save();
		
		$this->selectorRenderer = new SelectorRenderer($this, new Config($this->getDataFolder()."styles.yml", Config::YAML, self::DEFAULT_STYLES));
		$this->selectorRenderer->setHideUnknown((bool) $this->serverSelectorCfg->get('hide-unknown'));
		$this->selectorRenderer->setHideOffline((bool) $this->serverSelectorCfg->get('hide-offline'));
		
		$this->selectorServersManager = new SelectorServersManager($this);
		$this->db = new Config($this->getDataFolder()."ServerSelectorDB.yml", Config::YAML, []); //TODO:betterDB
		foreach($this->db->getAll() as $savedServer){
			$server = SelectorServer::createFromSaveData($savedServer);
			if($server === null){
				$this->getLogger()->critical("Failed to construct server with data ".implode($savedServer, ",").": Is the permGroup not registered anymore?");
			}
			if(!$this->addServer($server)){
				throw new \InvalidStateException("Failure while registering server ".$server->getID().": Server already registered");
			}
		}
		
		$this->listener = new ServerSelectorListener($this, [$this->serverSelectorCfg->get('selector-open-items'), $this->serverSelectorCfg->get('selector-item-open-levels')]);
		$this->server->getPluginManager()->registerEvents($this->listener, $this);
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
	 * @return SelectorRenderer
	 */
	public function getSelectorRenderer(): SelectorRenderer{
		return $this->selectorRenderer;
	}

	/**
	 * @return SelectorServersManager
	 */
	public function getSelectorServersManager(): SelectorServersManager{
		return $this->selectorServersManager;
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
	 * @param Player $player
	 * @param bool   $force Ignores player permission and event cancellation
	 */
	public function openSelector(Player $player, bool $force = false){
		if($force || $player->hasPermission("ServerSelector.openList")){
			$event = new ServerSelectorOpenEvent($this, $player, $force);
			$this->server->getPluginManager()->callEvent($event);
			if($force || !$event->isCancelled()){
				$this->selectorRenderer->render($player);
			}
		}
	}
	
	/**
	 * @return SelectorServer[]
	 */
	public function getServers(): array{
		return $this->servers;
	}

	/**
	 * @param string $hostname
	 * @param int    $port
	 *
	 * @return null|SelectorServer
	 */
	public function getSelectorServer(string $hostname, int $port): ?SelectorServer{
		if(isset($this->servers[$hostname."@".$port])){
			return $this->servers[$hostname."@".$port];
		}
		return null;
	}
	
	/**
	 * @param SelectorServer       $server
	 * @param bool                 $save
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
	 * @param SelectorServer       $server
	 * @param bool                 $save
	 * @param null|SignServerStats $sss
	 *
	 * @return bool
	 */
	public function remServer(SelectorServer $server, bool $save = true, ?SignServerStats $sss = null): bool{
		if($sss === null){
			$sss = $this->getSSS();
		}
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
	 * @param null|string          $displayName The displayname which is available as a special var for styles.
	 * @param null|string          $permGroup The permgroup (discrete permission) or simply null (for all players)
	 * @param null|SignServerStats $sss
	 *
	 * @return bool
	 */
	public function addSelectorServer(string $hostname, int $port, ?string $displayName = null, ?string $permGroup = null, bool $save = true, ?SignServerStats $sss = null): bool{
		$server = new SelectorServer($hostname, $port, $displayName);
		if(!$server->setPermGroup($permGroup)){
			return false;
		}
		return $this->addServer($server, $save, $sss);
	}
	
	/**
	 * Removes a Server from the ServerSelector
	 *
	 * @param string		  $hostname
	 * @param int			  $port
	 * @param bool			  $save Whether the removal should be saved to disk and therefore whether the server
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

	public static function getHRServerName(string $hostname, int $port, string $terminate = TF::GREEN): string{
		return TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.$terminate;
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
		switch($command->getName()){
			case "serverselector add":
			case "serverselector rem":
			case "serverselector edit":
				if(isset($args[0])){
					$hostname = $args[0];
					$port = 19132;
					$nextUserArg = 1;
					if(isset($args[1])){
						if(is_numeric($args[1])){
							$port = $args[1];
							$nextUserArg = 2;
						}
					}
				}else{
					if($command->getName() === "serverselector edit"){
						$sender->sendMessage(TF::GREEN."Possible fields to edit: displayName, perm (Use /serverselector edit <hostname> [ip] <field> <value>)");
						return true;
					}
					return false;
				}
				if(($sss = $this->getSSS()) === null){
					$sender->sendMessage("Error. Please check the console.");
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
				$displayName = null;
				if(isset($args[$nextUserArg])){
					$displayName = $args[$nextUserArg];
				}
				if($this->addSelectorServer($hostname, $port, $displayName, null, $sss)){
					$sender->sendMessage(
						TF::GREEN."Successfully added the server ".
						self::getHRServerName($hostname, $port).
						" to the ServerSelector."
					);
				}else{
					$sender->sendMessage(
						TF::DARK_RED."The server ".
						self::getHRServerName($hostname, $port, TF::DARK_RED).
						" is already on the ServerSelector!"
					);
				}
				return true;
			break;
			case "serverselector rem":
				if($nextUserArg === 1 && isset($args[1])){
					return false;
				}
				if($this->remSelectorServer($hostname, $port, true, $sss)){
					$sender->sendMessage(
						TF::GREEN."Successfully removed the server ".
						TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::GREEN.
						" from the serverselector."
					);
				}else{
					$sender->sendMessage(
						TF::DARK_RED."The server ".
						self::getHRServerName($hostname, $port, TF::DARK_RED).
						" is not on the ServerSelector!"
					);
				}
				return true;
			break;
			case "serverselector edit":
				if(($selectorServer = $this->getSelectorServer($hostname, $port)) === null){
					$sender->sendMessage(
						TF::DARK_RED."The server ".
						self::getHRServerName($hostname, $port, TF::DARK_RED).
							" is not on the ServerSelector!"
						);
						return true;
				}
				if(!isset($args[$nextUserArg])){
					return false;
				}
				switch($args[$nextUserArg]){
					case "displayName":
						if(!isset($args[$nextUserArg+1])){
							$sender->sendMessage(TF::DARK_RED."Please supply a displayName");
							return true;
						}
						$selectorServer->setDisplayName($args[$nextUserArg+1]);
						$sender->sendMessage(
							TF::GREEN."Successfully set the displayName of the server ".
							self::getHRServerName($hostname, $port).
							" to ".TF::DARK_GRAY.$args[$nextUserArg+1].TF::GREEN."."
						);
					break;
					case "perm":
						if(!isset($args[$nextUserArg+1])){
							$sender->sendMessage(TF::DARK_RED."Please supply a permGroup (discrete permission)");
							return true;
						}
						if($selectorServer->setPermGroup($args[$nextUserArg+1])){
							$sender->sendMessage(
								TF::GREEN."Successfully set the perm of the server ".
								self::getHRServerName($hostname, $port).
								" to ".TF::DARK_GRAY.$args[$nextUserArg+1].TF::GREEN."."
							);
						}else{
							$sender->sendMessage(
								TF::DARK_RED."Failed to set the perm of the server ".
								self::getHRServerName($hostname, $port, TF::DARK_RED).
								" to ".TF::DARK_GRAY.$args[$nextUserArg+1].TF::DARK_RED.": Non-existent permission!"
							);
						}
					break;
					default:
						$sender->sendMessage(TF::GREEN."Possible fields to edit: displayName, perm (Use /serverselector edit <hostname> [ip] <field> <value>)");
					break;
				}
			break;
		}
		return false;
	}
}