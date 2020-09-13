<?php
declare(strict_types=1);
namespace BrontoPanel\BrontoServer\Network\Server;

use raklib\utils\InternetAddress;
use RuntimeException;

class TCPServerSocket
{
	/** @var resource */
	private $socket;

	/**
	 * @var InternetAddress
	 */
	private $bindAddress;

	public function __construct(InternetAddress $bindAddress)
	{
		$this->bindAddress = $bindAddress;
		$socket = @socket_create($bindAddress->version === 4 ? AF_INET : AF_INET6, SOCK_DGRAM, SOL_UDP);
		if ($socket === false){
			throw new RuntimeException("Failed to create socket: " . trim(socket_strerror(socket_last_error())));
		}
		$this->socket = $socket;
		if($bindAddress->version === 6){
			socket_set_option($this->socket, IPPROTO_IPV6, IPV6_V6ONLY, 1); //Don't map IPv4 to IPv6, the implementation can create another RakLib instance to handle IPv4
		}
		if(@socket_bind($this->socket, $bindAddress->ip, $bindAddress->port) === true){
			$this->setSendBuffer(1024 * 1024 * 8)->setRecvBuffer(1024 * 1024 * 8);
		}else{
			$error = socket_last_error($this->socket);
			if($error === SOCKET_EADDRINUSE){ //platform error messages aren't consistent
				throw new RuntimeException("Failed to bind socket: Something else is already running on $bindAddress");
			}
			throw new RuntimeException("Failed to bind to " . $bindAddress . ": " . trim(socket_strerror(socket_last_error($this->socket))));
		}
		socket_set_nonblock($this->socket);
	}
	public function getBindAddress() : InternetAddress{
		return $this->bindAddress;
	}

	/**
	 * @return resource
	 */
	public function getSocket(){
		return $this->socket;
	}

	public function close() : void{
		socket_close($this->socket);
	}
	public function getLastError() : int{
		return socket_last_error($this->socket);
	}
	public function readPacket(?string &$buffer, ?string &$source, ?int &$port){
		return @socket_recvfrom($this->socket, $buffer, 65535, 0, $source, $port);
	}
	public function writePacket(string $buffer, string $dest, int $port){
		return socket_sendto($this->socket, $buffer, strlen($buffer), 0, $dest, $port);
	}
	public function setSendBuffer(int $size){
		@socket_set_option($this->socket, SOL_SOCKET, SO_SNDBUF, $size);

		return $this;
	}
	public function setRecvBuffer(int $size){
		@socket_set_option($this->socket, SOL_SOCKET, SO_RCVBUF, $size);

		return $this;
	}
}