<?php

declare(strict_types=1);

namespace BrontoPanel\BrontoServer;

use pocketmine\plugin\PluginBase;

class Main extends PluginBase{
	public function onEnable()
	{
		$thread = new SecondThread();
		$thread->start(1118481);
	}
}
