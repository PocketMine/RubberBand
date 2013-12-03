<?php

/**
 *
 * ______      _     _              ______                 _ 
 * | ___ \    | |   | |             | ___ \               | |
 * | |_/ /   _| |__ | |__   ___ _ __| |_/ / __ _ _ __   __| |
 * |    / | | | '_ \| '_ \ / _ \ '__| ___ \/ _` | '_ \ / _` |
 * | |\ \ |_| | |_) | |_) |  __/ |  | |_/ / (_| | | | | (_| |
 * \_| \_\__,_|_.__/|_.__/ \___|_|  \____/ \__,_|_| |_|\__,_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link https://github.com/PocketMine/RubberBand/
 * 
 *
*/

class RubberBandManager extends Thread{
	public static $instance = false;
	private $stop;
	private $address;
	private $port;
	private $apiKey;
	private $frontendThread;
	private $backendThread;
	private $RC4;
	public $identifiers;
	public $router;
	public $data = array();
	
	public function __construct($address, $port, $apiKey){		
		$this->data = array($address, $port, $apiKey);
		$this->identifiers = new StackableArray();
		$this->router = new StackableArray();
		self::$instance = $this;
	}
	
	public function getIdentifier($address, $port){
		if(!isset($this->identifiers[$address.":".$port])){
			return $this->identifiers[$address.":".$port] = crc32(hash("sha1", $address.":".$port."|".$this->apiKey, true));
		}
		return $this->identifiers[$address.":".$port];
	}
	
	public function routePacket(StackablePacket $packet, $frontend = false){
		console("[DUMMY] Routing packet");
	}
	
	public function generateControlPacket($payload, $dstaddress, $dstport, $validUntil = false){
		$validUntil = $validUntil === false ? time() + DEFAULT_CONTROL_PACKET_TIME:(int) $validUntil;
		$payload = Utils::writeInt($validUntil) . $payload;
		$md5sum = md5($payload . $this->apiKey, true);
		$packet = new StackablePacket(chr(0xff).$this->RC4->encrypt($md5sum . $payload), false, false);
		$packet->dstaddress = $dstaddress;
		$packet->dstport = $dstport;
		$packet->len = strlen($packet->buffer);
		return $packet;
	}

	public function handleControlPacket(StackablePacket $packet){		
		$packet->buffer = $this->RC4->decrypt(substr($packet->buffer, 1));
		$md5sum = substr($packet->buffer, 0, 16);
		$offset = 0;
		$payload = substr($packet->buffer, 16);
		if(strlen($payload) == 0 or md5($payload . $this->apiKey, true) != $md5sum){ //Packet validity check
			return false;
		}
		$validUntil = Utils::readInt(substr($payload, $offset, 4));
		$offset += 4;
		if($validUntil < time()){ //Protect against packet replay
			$error = "packet.expired";
			return $this->generateControlPacket(chr(0x00).chr(strlen($error)).$error, $packet->address, $packet->port);
		}

		switch(ord($payload{$offset++})){

			case 0x00: //Error
				$len = ord($payload{$offset++});
				console("[NOTICE] Got \"".substr($payload, $offset, $len)."\" error from ".$packet->address.":".$packet->port);
				$offset += $len;
				break;

			case 0x01: //Node Identify
				$len = ord($payload{$offset++});
				$rubberVersion = substr($payload, $offset, $len);
				$offset += $len;
				if($rubberVersion != RUBBERBAND_VERSION){ //Only allow same version on all the network
					$error = "rubber.version";
					return $this->generateControlPacket(chr(0x00).chr(strlen($error)).$error, $packet->address, $packet->port);
				}
				
				$len = Utils::readShort(substr($payload, $offset, 2));
				$offset += 2;
				$server = substr($payload, $offset, $len);
				$offset += $len;
				
				$len = Utils::readShort(substr($payload, $offset, 2));
				$offset += 2;
				$group = substr($payload, $offset, $len);
				$offset += $len;
				
				$bitFlags = Utils::readInt(substr($payload, $offset, 4));
				$offset += 4;
				
				$defaultServer = ($bitFlags & 0x00000001) > 0;
				$defaultGroup  = ($bitFlags & 0x00000002) > 0;
				console("[INFO] Server \"{$server}\" on group \"{$group}\" (dS:".intval($defaultServer).",dG:".intval($defaultGroup).") has been identified");
				
				return $this->generateControlPacket(chr(0x02), $packet->address, $packet->port);
				break;

			default: //No identified packet
				$error = "packet.unknown";
				return $this->generateControlPacket(chr(0x00).chr(strlen($error)).$error, $packet->address, $packet->port);

		}
	}
	
	public function processFrontendPacket(StackablePacket $packet){
		$packet->identifier = $this->getIdentifier($packet->address, $packet->port);
		switch(ord($packet->buffer{0})){			
			//Raknet Packets
			case 0x01:
			case 0x02:
			case 0x05:
			case 0x06:
			case 0x07:
			case 0x08:
			case 0x1a:
			case 0x1c:
			case 0x1d:
			case 0x80:
			case 0x81:
			case 0x82:
			case 0x83:
			case 0x84:
			case 0x85:
			case 0x86:
			case 0x87:
			case 0x88:
			case 0x89:
			case 0x8a:
			case 0x8b:
			case 0x8c:
			case 0x8d:
			case 0x8e:
			case 0x8f:
			case 0x99:
			case 0xa0:
			case 0xc0:
				$this->routePacket($packet, true);
				break;
			case 0xfe:
				if($packet->buffer{1} == "\xfd"){ //Query
					//$this->handleQueryRequest();
					break;
				}
				break;
				
			case 0xff://RubberBand control packet
				if(($returnPacket = $this->handleControlPacket($packet)) instanceof StackablePacket){
					$this->frontendThread->sendPacket($returnPacket);
				}
				break;
			default: //No identified packets
				return false;
		}
		return true;
	}

	public function run(){
		$this->stop = false;
		$this->address = $this->data[0];
		$this->port = $this->data[1];
		$this->apiKey = $this->data[2];
		
		
		$this->RC4 = new RubberBandRC4(sha1($this->apiKey, true) ^ md5($this->apiKey, true));

		$this->frontendThread = new RubberBandFrontend($this->address, $this->port, $this);

		while(!$this->frontendThread->isRunning() and !$this->frontendThread->isTerminated()){
			usleep(1);
		}		
		while(!$this->frontendThread->isWaiting() and !$this->frontendThread->isTerminated()){
			usleep(1);
		}		
		if($this->frontendThread->isTerminated()){
			return 1;
		}
		
		$this->backendThread = new RubberBandBackend($this);
		while(!$this->backendThread->isRunning() and !$this->backendThread->isTerminated()){
			usleep(1);
		}
		while(!$this->backendThread->isWaiting() and !$this->backendThread->isTerminated()){
			usleep(1);
		}		
		if($this->backendThread->isTerminated()){
			return 1;
		}
		
		$this->backendThread->notify();
		$this->frontendThread->notify();

		while($this->stop == false){
			if($this->frontendThread->isTerminated()){
				console("[WARNING] Frontend Thread crashed, restarting...");
				$this->frontendThread = new RubberBandFrontend($this->address, $this->port, $this);
				while(!$this->frontendThread->isRunning() and !$this->frontendThread->isTerminated()){
					usleep(1);
				}		
				while(!$this->frontendThread->isWaiting() and !$this->frontendThread->isTerminated()){
					usleep(1);
				}	
				$this->frontendThread->notify();
			}
			if($this->backendThread->isTerminated()){
				console("[WARNING] Backend Thread crashed, restarting...");
				$this->backendThread = new RubberBandBackend($this);
				while(!$this->backendThread->isRunning() and !$this->backendThread->isTerminated()){
					usleep(1);
				}
				while(!$this->backendThread->isWaiting() and !$this->backendThread->isTerminated()){
					usleep(1);
				}
			}
			usleep(10000);
		}
		$this->frontendThread->stop();
		$this->backendThread->stop();
		return 0;
	}
	
}