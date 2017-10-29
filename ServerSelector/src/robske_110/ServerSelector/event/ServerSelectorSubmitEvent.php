<?php
namespace robske_110\ServerSelector\event;

use pocketmine\event\Cancellable;

/** @todo IMPLEMENT (actionType) */
class ServerSelectorSubmitEvent extends ServerSelectorEvent implements Cancellable{
	public static $handlerList = null;
}