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
	public $backendAddress;
	public $apiKey;
	public $frontendThread;
	public $backendThreads;
	public $backendThreadCount;
	public $RC4;
	public $identifiers;
	public $clientData;
	public $backendRoutes;
	public $nodeIndex;
	public $backendPorts;
	public $portRotation;
	
	public function __construct($address, $port, $backendAddress, $backendThreads, $apiKey){		
		$this->address = $address;
		$this->port = $port;
		$this->backendAddress = $backendAddress;
		$this->apiKey = $apiKey;
		$this->identifiers = new StackableArray();
		$this->backendRoutes = new StackableArray();
		$this->backendThreadCount = $backendThreads;
		$this->backendPorts = new StackableArray();
		$this->portRotation = 0;
		$this->nodeIndex = new StackableArray();
		$this->nodeIndex[0] = new StackableArray(); //RAW Servers		
		$this->nodeIndex[1] = new StackableArray(); //Server names
		$this->nodeIndex[2] = new StackableArray(); //Groups
		$this->nodeIndex[3] = false; //Default server
		$this->nodeIndex[4] = false; //Default group
		$this->clientData = new StackableArray();
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
		
		if($this->nodeIndex[3] !== false and $defaultServer === true){ //Same defaultServer
			return false;
		}
		
		if($this->nodeIndex[4] !== false and $defaultGroup === true and $this->nodeIndex[4] !== $group){ //Same defaultGroup
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
			"",  //Players (8)
			$defaultServer,  //9
			$defaultGroup,  //10
			new StackableArray(), //Ports (11)
			new StackableArray() //Clients (12)
		);
		$this->nodeIndex[1][$server] = $this->nodeIndex[0][$identifier];
		if(!isset($this->nodeIndex[2][$group])){
			$this->nodeIndex[2][$group] = new StackableArray();
		}
		$this->nodeIndex[2][$group][$identifier] = $this->nodeIndex[0][$identifier];
		
		if($defaultServer === true){
			$this->nodeIndex[3] = $this->nodeIndex[0][$identifier];
		}		
		if($defaultGroup === true){
			$this->nodeIndex[4] = $group;
		}
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
		$data = $this->nodeIndex[0][$identifier];
		
		//TODO: handle player redirection on remove
		
		unset($this->nodeIndex[1][$data[4]]);
		unset($this->nodeIndex[2][$data[5]][$identifier]);
		if(count($this->nodeIndex[2][$data[5]]) == 0){
			unset($this->nodeIndex[2][$data[5]]);
			if($data[5] === $this->nodeIndex[4]){ //Remove defaultGroup
				$this->nodeIndex[4] = false;
			}
		}
		unset($this->nodeIndex[0][$identifier]);
		if($data === $this->nodeIndex[3]){ //Remove defaultServer
			$this->nodeIndex[3] = false;
		}
		unset($data);
		return true;
	}
	
	public function getIdentifier($address, $port){
		if(!isset($this->identifiers[$address.":".$port])){
			return $this->identifiers[$address.":".$port] = crc32(hash("sha1", $address.":".$port."|".$this->apiKey, true));
		}
		return $this->identifiers[$address.":".$port];
	}	

	public function getAdvancedIdentifier($address, $port, $port2){
		if(!isset($this->identifiers[$address.":".$port.":".$port2])){
			return $this->identifiers[$address.":".$port.":".$port2] = crc32(hash("sha1", $address.":".$port.":".$port2."|".$this->apiKey, true));
		}
		return $this->identifiers[$address.":".$port.":".$port2];
	}
	
	public function generateControlPacket($payload, $dstaddress, $dstport, $validUntil = false){
		$validUntil = $validUntil === false ? time() + DEFAULT_CONTROL_PACKET_TIME:(int) $validUntil;
		$payload = Utils::writeInt($validUntil) . $payload;
		$md5sum = md5($payload . $this->apiKey, true);
		$payload = chr(0xff).$this->RC4->encrypt($md5sum . $payload);
		$packet = new StackablePacket($payload, false, false, strlen($payload));
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
				
				$len = ord($payload{$offset++});
				$server = substr($payload, $offset, $len);
				$offset += $len;
				
				$len = ord($payload{$offset++});
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
	
	public function giveBackend($identifier){
		if($this->nodeIndex[4] !== false){ //Default group
			return $this->selectFromGroup($this->nodeIndex[4]);
		}elseif($this->nodeIndex[3] !== false){ //Default server
			return $this->nodeIndex[3][0];
		}else{
			return false;
		}
	}
	
	public function selectServer($server){
		if(!isset($this->nodeIndex[1][$server])){
			return false;
		}
		return $this->nodeIndex[1][$server];
	}
	
	public function selectServerByIdentifier($identifier){
		if(!isset($this->nodeIndex[0][$identifier])){
			return false;
		}
		return $this->nodeIndex[0][$identifier];
	}
	
	public function selectFromGroup($group){
		if(!isset($this->nodeIndex[2][$group])){
			return false;
		}
		$servers = array();
		foreach($this->nodeIndex[2][$group] as $identifier => $server){
			if($server[6] < $server[7]){
				return $server[0];
			}
			$servers[] = $server;
		}
		return $servers[mt_rand(0, count($servers) - 1)][0];
	}
	
	public function generateRoute(StackablePacket $packet, $serverIdentifier = false){
		if($serverIdentifier === false and ($serverIdentifier = $this->giveBackend($packet->identifier)) === false){
			return false;
		}

		$assignedPort = false;
		foreach($this->backendPorts as $port => $backend){
			if(!isset($this->nodeIndex[0][$serverIdentifier][11][$port])){
				$assignedPort = $port;
				break;
			}
		}
		if($assignedPort === false){
			$this->portRotation = ++$this->portRotation % $this->backendThreadCount;
			$assignedPort = $this->backendThreads[$this->portRotation]->addSocket();
			if($assignedPort !== false){
				$this->backendPorts[$assignedPort] = $this->backendThreads[$this->portRotation];
			}else{
				return false;
			}
		}
		$this->nodeIndex[0][$serverIdentifier][11][$assignedPort] = $packet->identifier;
		$this->nodeIndex[0][$serverIdentifier][12][$packet->identifier] = $assignedPort;
		$sourceIdentifier = $this->getAdvancedIdentifier($this->nodeIndex[0][$serverIdentifier][1], $this->nodeIndex[0][$serverIdentifier][2], $assignedPort);
		$this->clientData[$packet->identifier] = new StackableArray(
			$packet->address, //0
			$packet->port, //1
			$this->nodeIndex[0][$serverIdentifier], //Frontend route (2)
			$sourceIdentifier //3
		);
		$this->backendRoutes[$sourceIdentifier] = $this->clientData[$packet->identifier];
		return true;

	}
	
	public function getFrontendToBackendRoute(StackablePacket $packet){
		if(!isset($this->clientData[$packet->identifier]) and $this->generateRoute($packet) !== true){ //New route
			return false;
		}
		return $this->clientData[$packet->identifier][2];
	}
	
	public function getBackendToFrontendRoute(StackablePacket $packet){
		if(!isset($this->backendRoutes[$packet->srcidentifier])){
			return false;
		}
		return $this->clientData[$packet->srcidentifier];
	}
	
	public function removeFrontendToBackendRoute($identifier){
		if(isset($this->clientData[$identifier])){
			//TODO: Send remove packet
			$server = $this->clientData[$identifier][2];
			unset($server[11][$server[12][$identifier]]);
			unset($server[12][$identifier]);
			$this->clientData[$identifier][2] = false;
		}
	}
	
	public function routePacket(StackablePacket $packet, $frontend = false){
		if($frontend === true){
			$route = $this->getFrontendToBackendRoute($packet);
			if($route === false){
				return false;
			}
			$packet->dstaddress = $route[1];
			$packet->dstport = $route[2];
			$srcport = $route[12][$packet->identifier];
			return $this->backendPorts[$srcport]->sendPacket($srcport, $packet);
		}else{
			$route = $this->getBackendToFrontendRoute($packet);
			if($route === false){
				return false;
			}
			$packet->dstaddress = $route[0];
			$packet->dstport = $route[1];
			$this->frontendThread->sendPacket($packet);
		}
	}
	
	public function processBackendPacket(StackablePacket $packet){
		$packet->identifier = $this->getIdentifier($packet->address, $packet->port);
		$packet->srcidentifier = $this->getAdvancedIdentifier($packet->address, $packet->port, $packet->srcport);
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
				$this->routePacket($packet, false);
				break;
			default: //No identified packets
				return false;
		}
		return true;
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
				if(isset($packet->buffer{1}) and $packet->buffer{1} == "\xfd"){ //Query
					//$this->handleQueryRequest();
					break;
				}
				break;
				
			case 0xff://RubberBand control packet
				if(($returnPacket = $this->handleControlPacket($packet)) instanceof StackablePacket){
					$this->frontendThread->sendPacket($returnPacket);
				}
				unset($returnPacket);
				break;
			default: //No identified packets
				return false;
		}
		return true;
	}

	public function run(){
		$this->stop = false;		
		
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
			$this->backendThreads[$k] = new RubberBandBackend($this, $this->backendAddress, $k);
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
					$sockets = $this->backendThreads[$k]->sockets;
					$this->backendThreads[$k] = new RubberBandBackend($this, $this->backendAddress, $k);
					while(!$this->backendThreads[$k]->isRunning() and !$this->backendThreads[$k]->isTerminated()){
						usleep(1);
					}
					while(!$this->backendThreads[$k]->isWaiting() and !$this->backendThreads[$k]->isTerminated()){
						usleep(1);
					}
					$this->backendThreads[$k]->sockets = $sockets;
					foreach($sockets as $port => $socket){
						$this->backendPorts[$port] = $this->backendThreads[$k];
					}
					unset($sockets);
					$this->backendThreads[$k]->notify();
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