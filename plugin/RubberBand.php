<?php

/*
__PocketMine Plugin__
name=RubberBand
description=Plugin used to communicate with the RubberBand frontend
version=1.0dev
author=PocketMine Team
class=RubberBand
apiversion=12
*/



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
		
class RubberBand implements Plugin{
	const VERSION = "1.0dev";
	const DEFAULT_CONTROL_PACKET_TIME = 10;

	private $server;
	private $RC4;
	private $apiKey;
	private $address;
	private $port;
	private $lastConnection = 0;
	private $connected = false;
	private $clients = array();

	public function __construct(ServerAPI $api, $server = false){
		$this->server = ServerAPI::request();
		RubberBandAPI::setInstance($this);
	}

	public function init(){
		$this->config = new Config($this->server->api->plugin->configPath($this)."config.yml", CONFIG_YAML, array(
			"api-key" => "API_KEY",
			"proxy-address" => "",
			"proxy-port" => 19132,
			"server" => "UNIQUE_IDENTIFIER",
			"group" => "default",
			"isDefaultServer" => true,
			"isDefaultGroup" => false,
		));
		
		if($this->config->get("api-key") === false or $this->config->get("api-key") == "API_KEY"){
			console("[ERROR] [RubberBand] API key not set. RubberBand won't work without setting it on the config.yml");
			return;
		}
		$this->apiKey = $this->config->get("api-key");

		if($this->config->get("server") === false or $this->config->get("server") == "UNIQUE_IDENTIFIER"){
			console("[ERROR] [RubberBand] Server not set. RubberBand won't work without setting it on the config.yml");
			return;
		}		
		
		if($this->config->get("proxy-address") === false or $this->config->get("proxy-address") == ""){
			console("[ERROR] [RubberBand] Proxy address not set. RubberBand won't work without setting it on the config.yml");
			return;
		}
		$this->address = $this->config->get("proxy-address");
		$this->port = (int) $this->config->get("proxy-port");
		
		$this->RC4 = new RubberBandRC4(sha1($this->apiKey, true) ^ md5($this->apiKey, true));

		PacketSendEvent::register(array($this, "sendPacketProxy"), EventPriority::HIGHEST);
		PacketReceiveEvent::register(array($this, "controlPacketHandler"), EventPriority::HIGHEST);
		$this->server->addHandler("server.close", array($this, "onServerStop"), 10);

		console("[INFO] [RubberBand] Connecting with frontend proxy [/{$this->address}:{$this->port}]...");
		$this->server->schedule(20 * 10, array($this, "scheduler"), array(), true);
		$this->sendNodeIdentifyPacket();
	}
	
	public function generateControlPacket($payload, $validUntil = false){
		$validUntil = $validUntil === false ? time() + self::DEFAULT_CONTROL_PACKET_TIME:(int) $validUntil;
		$payload = Utils::writeInt($validUntil) . $payload;
		$md5sum = md5($payload . $this->apiKey, true);
		$pk = new Packet;
		$pk->buffer = "\xff" . $this->RC4->encrypt($md5sum . $payload);
		return $pk;
	}
	
	public function sendNodeIdentifyPacket(){
		$payload = "";
		$payload .= chr(strlen(self::VERSION)).self::VERSION;
		$payload .= chr(strlen($this->config->get("server"))).$this->config->get("server");
		$payload .= chr(strlen($this->config->get("group"))).$this->config->get("group");
		$payload .= Utils::writeShort(count($this->server->clients));
		$payload .= Utils::writeShort($this->server->maxClients);
		$payload .= Utils::writeShort(CURRENT_PROTOCOL);
		$bitFlags = 0;
		$bitFlags |= $this->config->get("isDefaultServer") == true ? 0x00000001:0;
		$bitFlags |= $this->config->get("isDefaultGroup") == true ? 0x00000002:0;
		$payload .= Utils::writeInt($bitFlags);

		$pk = $this->generateControlPacket(chr(0x01).$payload);
		$pk->ip = $this->address;
		$pk->port = $this->port;
		return $this->server->send($pk);
	}
	
	public function sendNodePingPacket(){
		$payload = "";
		$payload .= Utils::writeShort(count($this->server->clients));
		$payload .= Utils::writeShort($this->server->maxClients);
		$players = "";
		foreach($this->server->clients as $player){
			if($player->username != ""){
				$players .= $player->username.",";
			}
		}
		$players = gzdeflate($players, 9);
		$payload .= Utils::writeShort(strlen($players)) . $players;
		
		$pk = $this->generateControlPacket(chr(0x03).$payload);
		$pk->ip = $this->address;
		$pk->port = $this->port;
		return $this->server->send($pk);
	}

	public function sendNodeRemovePacket(){
		$pk = $this->generateControlPacket(chr(0x05));
		$pk->ip = $this->address;
		$pk->port = $this->port;
		return $this->server->send($pk);
	}
		
	public function sendErrorPacket($error, $address, $port){
		$pk = $this->generateControlPacket(chr(0x00).chr(strlen($error)).$error);
		$pk->ip = $address;
		$pk->port = $port;
		return $this->server->send($pk);
	}
	
	public function controlPacketHandler(PacketReceiveEvent $event){
		$packet = $event->getPacket();
		if($packet->buffer{0} !== "\xff"){
			return;
		}
		$buffer = $this->RC4->decrypt(substr($packet->buffer, 1));
		$md5sum = substr($buffer, 0, 16);
		$offset = 0;
		$payload = substr($buffer, 16);
		if(strlen($payload) == 0 or md5($payload . $this->apiKey, true) != $md5sum){ //Packet validity check
			return false;
		}
		$validUntil = Utils::readInt(substr($payload, $offset, 4));
		$offset += 4;
		if($validUntil < time()){ //Protect against packet replay
			$error = "packet.expired";
			$this->sendErrorPacket($error, $packet->ip, $packet->port);
			return true;
		}

		switch(ord($payload{$offset++})){

			case 0x00: //Error
				$len = ord($payload{$offset++});
				console("[NOTICE] [RubberBand] Got \"".substr($payload, $offset, $len)."\" error from ".$packet->ip.":".$packet->port);
				$offset += $len;
				break;

			case 0x04: //Node Ping accepted
				if($this->connected !== true){
					break;
				}
			case 0x02: //Node Identify accepted			
				if($this->connected !== true){
					console("[INFO] [RubberBand] Connected correctly");
				}
				$this->connected = true;
				$this->lastConnection = time();
				break;

			case 0x06: //Node Remove accepted:
				break;

			case 0x08: //Player Change Target request			
				$transactionID = Utils::readInt(substr($payload, $offset, 4));
				$offset += 4;
				
				$len = ord($payload{$offset++});
				$originServer = substr($payload, $offset, $len);

				$len = ord($payload{$offset++});
				$originGroup = substr($payload, $offset, $len);
				$offset += $len;
				
				$len = ord($payload{$offset++});
				$realAddress = substr($payload, $offset, $len);
				$offset += $len;

				$len = ord($payload{$offset++});
				$realAddress = substr($payload, $offset, $len);
				$offset += $len;
				$realPort = Utils::readShort(substr($payload, $offset, 2));
				$offset += 2;
				
				$clientID = Utils::readLong(substr($payload, $offset, 8));
				$offset += 8;

				$MTU = Utils::readShort(substr($payload, $offset, 2));
				$offset += 2;
				
				$counter0 = Utils::readTriad(substr($payload, $offset, 3));
				$offset += 3;

				$counter3 = Utils::readTriad(substr($payload, $offset, 3));
				$offset += 3;

				$len = ord($payload{$offset++});
				$username = substr($payload, $offset, $len);

				$gamemode = ord($payload{$offset++});
				
				
				$len = ord($payload{$offset++});
				$spawnWorld = substr($payload, $offset, $len);
				$offset += $len;
				
				$spawnX = Utils::readFloat(substr($payload, $offset, 4));
				$offset += 4;

				$spawnY = Utils::readFloat(substr($payload, $offset, 4));
				$offset += 4;

				$spawnZ = Utils::readFloat(substr($payload, $offset, 4));
				$offset += 4;
				break;

			default: //No identified packet
				$error = "packet.unknown";
				$this->sendErrorPacket($error, $packet->ip, $packet->port);
				return true;

		}
		return true;
	}
	
	public function onServerStop(){
		$this->sendNodeRemovePacket();
		$this->connected = false;
	}
	
	public function scheduler(){
		if((time() - $this->lastConnection) > 30 and $this->connected === true){
			$this->connected = false;
			console("[WARNING] [RubberBand] Lost connection with frontend proxy. Reconnecting...");
			$this->sendNodeIdentifyPacket();
		}elseif($this->connected !== true){
			$this->sendNodeIdentifyPacket();
		}else{
			$this->sendNodePingPacket();
		}
	}

	public function __destruct(){
		$this->sendNodeRemovePacket();
	}
}

class RubberBandRC4{
	private $S;

	public function __construct($key, $drop = 768){
		for($i = 0; $i < 256; ++$i){
			$this->S[$i] = $i;
		}
		
		$j = 0;
		for($i = 0; $i < 256; ++$i){
			$j = ($j + $this->S[$i] + ord($key{$i % strlen($key)})) & 0xFF;
			$this->S[$i] ^= $this->S[$j];
			$this->S[$j] ^= $this->S[$i];
			$this->S[$i] ^= $this->S[$j];
		}
		
		$i = $j = 0;
		for($k = 0; $k < $drop; ++$k){
			$i = ($i + 1) & 0xFF;
			$j = ($j + $this->S[$i]) & 0xFF;
			$this->S[$i] ^= $this->S[$j];
			$this->S[$j] ^= $this->S[$i];
			$this->S[$i] ^= $this->S[$j];
		}
	}
	
	public function encrypt($text, $IV = false){
		$IV = $IV === false ? substr(md5(microtime().mt_rand(), true), mt_rand(0, 7), 8):substr($IV, 0, 8);
		$text = $IV . $text;
		$len = strlen($text);
		$i = $j = 0;
		$S = $this->S;
		$text0 = "\x00";
		for($k = 0; $k < $len; ++$k){
			$i = ($i + 1) & 0xFF;
			$j = ($j + $S[$i]) & 0xFF;
			$S[$i] ^= $S[$j];
			$S[$j] ^= $S[$i];
			$S[$i] ^= $S[$j];
			$K = chr($S[($S[$i] + $S[$j]) & 0xFF]) ^ $text0;
			$text{$k} = $text{$k} ^ $K;
			$text0 = $text{$k};
		}
		return $text;
	}
	
	public function decrypt($text){
		$len = strlen($text);
		$i = $j = 0;
		$S = $this->S;
		$text0 = "\x00";
		for($k = 0; $k < $len; ++$k){
			$i = ($i + 1) & 0xFF;
			$j = ($j + $S[$i]) & 0xFF;
			$S[$i] ^= $S[$j];
			$S[$j] ^= $S[$i];
			$S[$i] ^= $S[$j];
			$K = chr($S[($S[$i] + $S[$j]) & 0xFF]) ^ $text0;
			$text0 = $text{$k};
			$text{$k} = $text{$k} ^ $K;
		}
		return substr($text, 8);
	}

}

class RubberBandThread{

}

class RubberBandAPI{
	protected static $instance = false;

	public static function setInstance(RubberBand $instance){
		self::$instance = $instance;
	}

}
