<?php
declare(strict_types=1);

namespace BrontoPanel\BrontoServer\Network\Server;

use pocketmine\snooze\SleeperNotifier;
use raklib\RakLib;
use raklib\utils\InternetAddress;
use Threaded;
use ThreadedLogger;
use Throwable;

class RakLibServer
{
	/** @var InternetAddress */
	private $address;

	/** @var ThreadedLogger */
	protected $logger;

	/** @var string */
	protected $loaderPath;

	/** @var bool */
	protected $shutdown = false;

	/** @var Threaded */
	protected $externalQueue;
	/** @var Threaded */
	protected $internalQueue;

	/** @var int */
	protected $serverId = 0;
	/** @var int */
	protected $maxMtuSize;
	/** @var int */
	private $protocolVersion;

	/** @var SleeperNotifier|null */
	protected $mainThreadNotifier;

	/**
	 * @param ThreadedLogger $logger
	 * @param string $autoloaderPath Path to Composer autoloader
	 * @param InternetAddress $address
	 * @param int $maxMtuSize
	 * @param int|null $overrideProtocolVersion Optional custom protocol version to use, defaults to current RakLib's protocol
	 * @param SleeperNotifier|null $sleeper
	 */
	public function __construct(ThreadedLogger $logger, string $autoloaderPath, InternetAddress $address, int $maxMtuSize = 1492, ?int $overrideProtocolVersion = null, ?SleeperNotifier $sleeper = null){
		$this->address = $address;

		$this->serverId = mt_rand(0, PHP_INT_MAX);
		$this->maxMtuSize = $maxMtuSize;

		$this->logger = $logger;
		$this->loaderPath = $autoloaderPath;

		$this->externalQueue = new Threaded;
		$this->internalQueue = new Threaded;

		$this->protocolVersion = $overrideProtocolVersion ?? RakLib::DEFAULT_PROTOCOL_VERSION;

		$this->mainThreadNotifier = $sleeper;
	}

	public function isShutdown() : bool{
		return $this->shutdown === true;
	}

	public function shutdown() : void{
		$this->shutdown = true;
	}

	/**
	 * Returns the RakNet server ID
	 */
	public function getServerId() : int{
		return $this->serverId;
	}

	public function getProtocolVersion() : int{
		return $this->protocolVersion;
	}

	public function getLogger() : ThreadedLogger{
		return $this->logger;
	}

	public function getExternalQueue() : Threaded{
		return $this->externalQueue;
	}

	public function getInternalQueue() : Threaded{
		return $this->internalQueue;
	}

	public function pushMainToThreadPacket(string $str) : void{
		$this->internalQueue[] = $str;
	}

	public function readMainToThreadPacket() : ?string{
		return $this->internalQueue->shift();
	}

	public function pushThreadToMainPacket(string $str) : void{
		$this->externalQueue[] = $str;
		if($this->mainThreadNotifier !== null){
			$this->mainThreadNotifier->wakeupSleeper();
		}
	}

	public function readThreadToMainPacket() : ?string{
		return $this->externalQueue->shift();
	}

	/**
	 * @return void
	 */
	public function shutdownHandler(){
		if($this->shutdown !== true){
			$error = error_get_last();
			if($error !== null){
				$this->logger->emergency("Fatal error: " . $error["message"] . " in " . $error["file"] . " on line " . $error["line"]);
			}else{
				$this->logger->emergency("RakLib shutdown unexpectedly");
			}
		}
	}

	public function run() : void{
		try{
			$socket = new TCPServerSocket($this->address);
			new SessionManager($this, $socket, $this->maxMtuSize);
		}catch(Throwable $e){
			$this->logger->logException($e);
		}
	}
}