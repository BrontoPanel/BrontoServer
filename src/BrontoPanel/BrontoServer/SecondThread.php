<?php
declare(strict_types=1);

namespace BrontoPanel\BrontoServer;


use BrontoPanel\BrontoServer\Network\Server\RakLibServer;
use pocketmine\Server;
use raklib\utils\InternetAddress;
use Thread;
use const pocketmine\COMPOSER_AUTOLOADER_PATH;

class SecondThread extends Thread
{
	private $loops = 0;
	public function run()
	{
		parent::run();
		require_once(__DIR__."vendor/AutoLoad.php");
		if ($this->loops == 0){
			$server = new RakLibServer(Server::getInstance()->getLogger(), COMPOSER_AUTOLOADER_PATH, new InternetAddress("0.0.0.0", 20000, 4));
			$server->run();
		}
		$this->loops++;
	}
}