<?php
namespace robske_110\ServerSelector;

use pocketmine\form\Form;
use pocketmine\form\MenuForm;
use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat as TF;
use pocketmine\Player;

class ServerSelectorForm extends MenuForm{
	/** @var array */
	private $options;
	/** @var Player */
	private $player;
	/** @var SelectorRenderer */
	private $selectorRenderer;
	
	public function __construct(Player $player, array $optionResponses, SelectorRenderer $selectorRenderer, string $title, string $text, array $options){
		$this->options = $optionResponses;
		$this->player = $player;
		$this->selectorRenderer = $selectorRenderer;
		parent::__construct($title, $text, ...$options);
	}
	
	public function onSubmit(Player $player): ?Form{
		if(!$this->selectorRenderer->getPlugin()->isEnabled()){
			return null;
		}
		if($player->getId() === $this->player->getId()){
			$selectedOption = $this->getSelectedOptionIndex();
			if(isset($this->options[$selectedOption])){
				switch($this->options[$selectedOption][0]){
					case 0: //refresh
						$this->selectorRenderer->render($player);
					break;
					case 1: //transfer
						$this->selectorRenderer->getPlugin()->getServer()->getScheduler()->scheduleDelayedTask(
							new class($this->selectorRenderer->getPlugin(), $player, $this->options[$selectedOption][1], $this->options[$selectedOption][2]) extends PluginTask{
								/** @var Player */
								private $player;
								/** @var string */
								private $ip;
								/** @var int */
								private $port;
									
								public function __construct(ServerSelector $plugin, Player $player, string $ip, int $port){
									parent::__construct($plugin);
									$this->player = $player;
									$this->ip = $ip;
									$this->port = $port;
								}
	
								public function onRun(int $currentTick){
									$this->player->transfer($this->ip, $this->port, "ServerSelectorTransfer/".implode("@", [$this->ip, $this->port]));
								}
							},
							1
						);
					break;
					case 2: //offline server
						$player->sendMessage(
							TF::RED."The server ".TF::GREEN.$this->options[$selectedOption][1].TF::DARK_GRAY.":".
							TF::GREEN.$this->options[$selectedOption][2].TF::RED." is offline."
						);
					break;
				}
			}
		}
		return null;
	}
}