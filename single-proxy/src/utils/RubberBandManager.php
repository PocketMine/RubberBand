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
	public $stop;
	public $address;
	public $port;
	public $apiKey;
	public $frontendThread;
	public $backendThreads;
	public $backendThreadCount;
	public $RC4;
	public $identifiers;
	public $router;
	public $nodeIndex;
	public $data = array();
	
	public function __construct($address, $port, $backendThreads, $apiKey){		
		$this->data = array($address, $port, $apiKey);
		$this->identifiers = new StackableArray();
		$this->router = new StackableArray();
		$this->backendThreadCount = $backendThreads;
		$this->nodeIndex = new StackableArray();
		$this->nodeIndex[0] = new StackableArray(); //RAW Servers		
		$this->nodeIndex[1] = new StackableArray(); //Server names
		$this->nodeIndex[2] = new StackableArray(); //Groups
		self::$instance = $this;
	}
	
	public function addNode($address, $port, $server, $group, $onlinePlayers, $maxPlayerCount, $defaultServer, $defaultGroup){
		$identifier = $this->getIdentifier($address, $port);
		if(isset($this->nodeIndex[0][$identifier])){
			return false;
		}
	
		if(isset($this->nodeIndex[1][$server])){
			return false;
		}
		
		$this->nodeIndex[0][$identifier] = new StackableArray(
			$identifier,  //0
			$address,  //1
			$port,  //2
			time() + 30, //Timeout (3)
			$server,  //4
			$group,  //5
			$onlinePlayers,  //6
			$maxPlayerCount,  //7
			"",  //8
			$defaultServer,  //9
			$defaultGroup  //10
		);
		$this->nodeIndex[1][$server] = $this->nodeIndex[0][$identifier];
		if(!isset($this->nodeIndex[2][$group])){
			$this->nodeIndex[2][$group] = new StackableArray();
		}
		$this->nodeIndex[2][$group][$identifier] = $this->nodeIndex[0][$identifier];
		return true;
	}
	
	public function updateNode($address, $port, $onlinePlayers, $maxPlayerCount, $players){
		$identifier = $this->getIdentifier($address, $port);
		if(!isset($this->nodeIndex[0][$identifier])){
			return false;
		}
		$this->nodeIndex[0][$identifier][3] = time() + 30;
		$this->nodeIndex[0][$identifier][6] = $onlinePlayers;
		$this->nodeIndex[0][$identifier][7] = $maxPlayerCount;
		$this->nodeIndex[0][$identifier][8] = $players;
		return true;
	}

	public function removeNode($address, $port){
		$identifier = $this->getIdentifier($address, $port);
		if(!isset($this->nodeIndex[0][$identifier])){
			return false;
		}
		$data =& $this->nodeIndex[0][$identifier];
		
		//TODO: handle player redirection
		
		unset($this->nodeIndex[1][$data[4]]);
		unset($this->nodeIndex[2][$data[5]][$identifier]);
		if(count($this->nodeIndex[2][$data[5]]) == 0){
			unset($this->nodeIndex[2][$data[5]]);
		}
		unset($this->nodeIndex[0][$identifier]);
		return true;
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
				
				$playerCount = Utils::readShort(substr($payload, $offset, 2));
				$offset += 2;
				
				$maxPlayerCount = Utils::readShort(substr($payload, $offset, 2));
				$offset += 2;
				
				$bitFlags = Utils::readInt(substr($payload, $offset, 4));
				$offset += 4;
				
				$defaultServer = ($bitFlags & 0x00000001) > 0;
				$defaultGroup  = ($bitFlags & 0x00000002) > 0;
				if($this->addNode($packet->address, $packet->port, $server, $group, $playerCount, $maxPlayerCount, $defaultServer, $defaultGroup) !== false){
					console("[INFO] Node [/{$packet->address}:{$packet->port}] \"{$server}\" on group \"{$group}\" (dS:".intval($defaultServer).",dG:".intval($defaultGroup).") has been identified");
					return $this->generateControlPacket(chr(0x02), $packet->address, $packet->port);
				}else{
					$error = "node.add";
					return $this->generateControlPacket(chr(0x00).chr(strlen($error)).$error, $packet->address, $packet->port);				
				}
				
			case 0x03: //Node Ping
				$playerCount = Utils::readShort(substr($payload, $offset, 2));
				$offset += 2;
				
				$maxPlayerCount = Utils::readShort(substr($payload, $offset, 2));
				$offset += 2;
				
				$len = Utils::readShort(substr($payload, $offset, 2));
				$players = gzinflate(substr($payload, $offset, $len));
				$offset += $len;
				if($this->updateNode($packet->address, $packet->port, $playerCount, $maxPlayerCount, $players) !== false){
					return $this->generateControlPacket(chr(0x04), $packet->address, $packet->port);
				}else{
					$error = "node.update";
					return $this->generateControlPacket(chr(0x00).chr(strlen($error)).$error, $packet->address, $packet->port);				
				}
			
			case 0x05: //Node remove
				if($this->removeNode($packet->address, $packet->port) !== false){
					console("[INFO] Node [/{$packet->address}:{$packet->port}] has been removed");
					return $this->generateControlPacket(chr(0x06), $packet->address, $packet->port);
				}else{
					$error = "node.remove";
					return $this->generateControlPacket(chr(0x00).chr(strlen($error)).$error, $packet->address, $packet->port);				
				}

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
		
		$this->backendThreads = new StackableArray();
		
		for($k = 0; $k < $this->backendThreadCount; ++$k){
			$this->backendThreads[$k] = new RubberBandBackend($this, $k);
			while(!$this->backendThreads[$k]->isRunning() and !$this->backendThreads[$k]->isTerminated()){
				usleep(1);
			}
			while(!$this->backendThreads[$k]->isWaiting() and !$this->backendThreads[$k]->isTerminated()){
				usleep(1);
			}
			
			$this->backendThreads[$k]->notify();
		}

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
			for($k = 0; $k < $this->backendThreadCount; ++$k){
				if($this->backendThreads[$k]->isTerminated()){
					console("[WARNING] Backend Thread #$k crashed, restarting...");
					$this->backendThreads[$k] = new RubberBandBackend($this, $k);
					while(!$this->backendThreads[$k]->isRunning() and !$this->backendThreads[$k]->isTerminated()){
						usleep(1);
					}
					while(!$this->backendThreads[$k]->isWaiting() and !$this->backendThreads[$k]->isTerminated()){
						usleep(1);
					}
				}
			}
			foreach($this->nodeIndex[0] as $identifier => $data){
				if($data[3] < time()){
					$this->removeNode($data[1], $data[2]);
					console("[INFO] Node [/{$data[1]}:{$data[2]}] has been removed");
					unset($data);
				}
			}
			usleep(10000);
		}
		$this->frontendThread->stop();
		$this->backendThread->stop();
		return 0;
	}
	
}