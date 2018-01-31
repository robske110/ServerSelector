<?php
namespace robske_110\ServerSelector;

use pocketmine\form\MenuOption;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\Config;

class SelectorRenderer{
	/** @var ServerSelector */
	private $plugin;
	/** @var Config */
	private $styleCfg;
	
	/** @var bool */
	private $hideUnknown;
	/** @var bool */
	private $hideOffline;
	
	/**
	 * @param ServerSelector $plugin
	 * @param Config $styleCfg
	 */
	public function __construct(ServerSelector $plugin, Config $styleCfg){
		$this->plugin = $plugin;
		$this->styleCfg = $styleCfg;
	}
	
	/**
	 * @param bool $hideUnknown
	 */
	public function setHideUnknown(bool $hideUnknown){
		$this->hideUnknown = $hideUnknown;
	}
	
	/**
	 * @param bool $hideOffline
	 */
	public function setHideOffline(bool $hideOffline){
		$this->hideOffline = $hideOffline;
	}
	
	/**
	 * @return ServerSelector
	 */
	public function getPlugin(): ServerSelector{
		return $this->plugin;
	}
	
	/**
	 * @param Player $player The player to render the Selector for
	 */
	public function render(Player $player){
		$serversData = $this->plugin->getSelectorServersManager()->getServers();
		$options = [];
		$optionResponses = [];
		foreach($this->plugin->getServers() as $selectorServer){
			if($selectorServer->canSee($player)){
				if(isset($serversData[$selectorServer->getID()])){
					$state = $serversData[$selectorServer->getID()][2] === true ? 1 : 2;
					if($state === 2 && $this->hideOffline){
						continue;
					}
					$options[] = new MenuOption(implode("\n".TF::RESET,$this->calcServerButtonLines($selectorServer, $serversData[$selectorServer->getID()])));
				}elseif(!$this->hideUnknown){
					$options[] = new MenuOption($this->parseStyle("selector.unknown.error")."\n".TF::WHITE.$selectorServer->getID());
					$state = 2;
				}
				$optionResponses[] = [$state, $selectorServer->getHostname(), $selectorServer->getPort()];
			}
		}
		$options[] = new MenuOption(
			$this->parseStyle("selector.refresh-button", [
					'lastrefresh' => round(($this->plugin->getServer()->getTick() - $this->plugin->getSelectorServersManager()->getStatusServerRefreshTick()) / 20, 1)
				]
			)
		);
		$optionResponses[] = [0];
		$form = new ServerSelectorForm($player, $optionResponses, $this, $this->parseStyle("selector.title"), $this->parseStyle("selector.introduction-text"), $options);
		$player->sendForm($form);
	}
	
	/**
	 * @param string $styleID
	 * @param array  $vars
	 *
	 * @return string
	 */
	private function parseStyle(string $styleID, array $vars = []): string{
		$varSep = $this->styleCfg->get("variableSeparator", "%");
		$str = $this->styleCfg->get($styleID, $styleID);
		foreach((new \ReflectionClass(TF::class))->getConstants() as $name => $value){
			$vars[$name] = $value;
		}
		foreach($vars as $name => $value){
			$str = str_replace($varSep.$name.$varSep, $value, $str);
		}
		return $str;
	}
	
	/**
	 * @param SelectorServer $selectorServer
	 * @param array|null     $serverData
	 *
	 * @return array $lines
	 */
	private function calcServerButtonLines(SelectorServer $selectorServer, ?array $serverData = null): array{
		$vars = [
			"ip" => $selectorServer->getHostname(),
			"port" => $selectorServer->getPort(),
			"playercount" => $this->styleCfg->get('selector.unknown.playercount'),
			"displayname" => $selectorServer->getDisplayName() ?? ""
		];
		if($serverData === null){
			$serverData = $this->plugin->getSelectorServersManager()->getServers()[$selectorServer->getID()];
		}
		$lines = [];
		if($serverData[2] === null){ //A new server has been added to the Selector and there is no info yet
			for($line = 0; $line < 2; $line++){
				$lines[$line] = $this->parseStyle("selector.loading.line".($line + 1), $vars);
			}
		}else{
			if($serverData[2]){
				$playerData = $serverData[3];
				$vars["modt"] = $serverData[4] ?? TF::DARK_RED."ERROR";
				$vars["playercount"] = $this->parseStyle('selector.known.playercount', [
					'currPlayers' => $playerData[0] ?? "-",
					'maxPlayers' => $playerData[1] ?? "-"
				]);
				
				for($line = 0; $line < 2; $line++){
					$lines[$line] = $this->parseStyle("selector.online.line".($line + 1), $vars);
				}
			}else{
				for($line = 0; $line < 2; $line++){
					$lines[$line] = $this->parseStyle("selector.offline.line".($line + 1), $vars);
				}
			}
		}
		for($line = 0; $line < 2; $line++){
			if(empty($lines[$line])){
				unset($lines[$line]);
			}
		}
		return $lines;
	}
}