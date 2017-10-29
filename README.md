# ServerSelector
A PocketMine plugin which shows a List with Servers along with their info and a transfer button.
The ServerSelector, with the default style looks like this:
![Example](https://raw.githubusercontent.com/robske110/ServerSelector/master/img/examplesmall.png)

This plugin depends on SignServerStats, you also have to have it installed!

## Usage:
Anyone with the permission `ServerSelector.openList` can open the server selector using the command /serverlist or using the item defined in the config.yml in the levels defined in config.yml

You can edit the style in styles.yml. Simply use %COLOUR% (as defined [here](https://github.com/pmmp/PocketMine-MP/blob/master/src/pocketmine/utils/TextFormat.php#L32-L54)) for colours and see the example style for special variables.

## API:
**This plugin has a powerful API, which can mainly modify the server list, prevent it to open under certain conditions, and open it for certain players.**

**For a "documentation", check the code.**

_You should always check if your plugin is compatible with the version of ServerSelector present on the current server with the help of the isCompatible function:_

```php
/** @var robske_110\ServerSelector\ServerSelector $serverSelector */
if(!$serverSelector->isCompatible("0.1.0")){
   	$this->getLogger()->critical("Your version of ServerSelector is not compatible with this plugin.");
	$this->getServer()->getPluginManager()->disablePlugin($this);
	return;
}
```