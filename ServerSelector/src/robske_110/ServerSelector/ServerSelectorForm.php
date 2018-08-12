<?php
namespace robske_110\ServerSelector;

use pocketmine\form\Form;
use pocketmine\form\MenuForm;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat as TF;
use pocketmine\Player;
use robske_110\ServerSelector\event\ServerSelectorCloseEvent;
use robske_110\ServerSelector\event\ServerSelectorSubmitEvent;

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
		parent::__construct($title, $text, $options);
	}
	
	public function onSubmit(Player $player, int $selectedOption): ?Form{
		if(!$this->selectorRenderer->getPlugin()->isEnabled()){
			return null;
		}
		if($player->getId() === $this->player->getId()){
			if(isset($this->options[$selectedOption])){
				switch($this->options[$selectedOption][0]){
					case 0: //refresh
						$event = new ServerSelectorSubmitEvent($this->selectorRenderer->getPlugin(), $player, $this->options[$selectedOption][0]);
						$this->selectorRenderer->getPlugin()->getServer()->getPluginManager()->callEvent($event);
						if(!$event->isCancelled()){
							$this->selectorRenderer->render($player);
						}
					break;
					case 1: //transfer
						$event = new ServerSelectorSubmitEvent($this->selectorRenderer->getPlugin(), $player, $this->options[$selectedOption][0], $this->options[$selectedOption][1], $this->options[$selectedOption][2]);
						$this->selectorRenderer->getPlugin()->getServer()->getPluginManager()->callEvent($event);
						if($event->isCancelled()){
							return null;
						}
						$this->selectorRenderer->getPlugin()->getScheduler()->scheduleDelayedTask(
							new class($player, $this->options[$selectedOption][1], $this->options[$selectedOption][2]) extends Task{
								/** @var Player */
								private $player;
								/** @var string */
								private $ip;
								/** @var int */
								private $port;

								public function __construct(Player $player, string $ip, int $port){
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
						$event = new ServerSelectorSubmitEvent($this->selectorRenderer->getPlugin(), $player, $this->options[$selectedOption][0], $this->options[$selectedOption][1], $this->options[$selectedOption][2]);
						$this->selectorRenderer->getPlugin()->getServer()->getPluginManager()->callEvent($event);
						if(!$event->isCancelled()){
							$player->sendMessage(
								TF::RED."The server ".TF::GREEN.$this->options[$selectedOption][1].TF::DARK_GRAY.":".
								TF::GREEN.$this->options[$selectedOption][2].TF::RED." is offline."
							);
						}
					break;
				}
			}
		}
		return null;
	}
	
	public function onClose(Player $player): ?Form{
		$event = new ServerSelectorCloseEvent($this->selectorRenderer->getPlugin(), $player);
		$this->selectorRenderer->getPlugin()->getServer()->getPluginManager()->callEvent($event);
		return parent::onClose($player);
	}
}