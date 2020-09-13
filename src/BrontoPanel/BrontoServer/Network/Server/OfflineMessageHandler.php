<?php


namespace BrontoPanel\BrontoServer\Network\Server;


use raklib\protocol\OfflineMessage;
use raklib\protocol\OpenConnectionReply1;
use raklib\protocol\OpenConnectionReply2;
use raklib\protocol\OpenConnectionRequest1;
use raklib\protocol\OpenConnectionRequest2;
use raklib\protocol\UnconnectedPing;
use raklib\protocol\UnconnectedPong;
use raklib\utils\InternetAddress;

class OfflineMessageHandler
{
	/** @var SessionManager */
	private $sessionManager;

	public function __construct(SessionManager $manager){
		$this->sessionManager = $manager;
	}

	public function handle(OfflineMessage $packet, InternetAddress $address) : bool{
		switch($packet::$ID){
			case UnconnectedPing::$ID:
				/** @var UnconnectedPing $packet */
				$pk = new UnconnectedPong();
				$pk->serverID = $this->sessionManager->getID();
				$pk->pingID = $packet->pingID;
				$pk->serverName = $this->sessionManager->getName();
				$this->sessionManager->sendPacket($pk, $address);
				return true;
			case OpenConnectionRequest1::$ID:
				/** @var OpenConnectionRequest1 $packet */
				$pk = new OpenConnectionReply1();
				$pk->mtuSize = $packet->mtuSize + 28; //IP header size (20 bytes) + UDP header size (8 bytes)
				$pk->serverID = $this->sessionManager->getID();
				$this->sessionManager->sendPacket($pk, $address);
				return true;
			case OpenConnectionRequest2::$ID:
				/** @var OpenConnectionRequest2 $packet */

				if($packet->serverAddress->port === $this->sessionManager->getPort() or !$this->sessionManager->portChecking){
					if($packet->mtuSize < Session::MIN_MTU_SIZE){
						$this->sessionManager->getLogger()->debug("Not creating session for $address due to bad MTU size $packet->mtuSize");
						return true;
					}
					$mtuSize = min($packet->mtuSize, $this->sessionManager->getMaxMtuSize()); //Max size, do not allow creating large buffers to fill server memory
					$pk = new OpenConnectionReply2();
					$pk->mtuSize = $mtuSize;
					$pk->serverID = $this->sessionManager->getID();
					$pk->clientAddress = $address;
					$this->sessionManager->sendPacket($pk, $address);
					$this->sessionManager->createSession($address, $packet->clientID, $mtuSize);
				}else{
					$this->sessionManager->getLogger()->debug("Not creating session for $address due to mismatched port, expected " . $this->sessionManager->getPort() . ", got " . $packet->serverAddress->port);
				}

				return true;
		}

		return false;
	}
}