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

class RubberBandBackend extends Thread{
	public $sockets, $manager, $id;
	public $stop;
	public $address;
	public $queue;
	public $hasPackets;
	
	public function __construct(RubberbandManager $manager, $address, $id = 0){
		$this->manager = $manager;
		$this->address = $address;
		$this->queue = new StackableArray();
		$this->hasPackets = new StackableArray();
		$this->id = $id;
		$this->start();
	}
	
	public function addSocket(){
		if(($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) !== false and socket_bind($socket, $this->address) !== false){
			$port = null;
			$address = null;
			socket_getsockname($socket, $address, $port);
			socket_set_option($socket, SOL_SOCKET, SO_SNDBUF, 65535);
			socket_set_option($socket, SOL_SOCKET, SO_RCVBUF, 65535);
			socket_set_nonblock($socket);
			$this->sockets[$port] = $socket;
			$this->queue[$port] = new StackableArray();
			$this->hasPackets[$port] = false;
			return $port;
		}
		return false;
	}
	
	public function sendPacket($srcport, StackablePacket $packet){
		if(!isset($this->sockets[$srcport])){
			return false;
		}
		$this->hasPackets[$srcport] = true;
		$this->queue[$srcport][] = $packet;
		return true;
	}
	
	public function stop(){
		$this->stop = true;
	}
	
	public function run(){
		$this->stop = false;
		$this->sockets = new StackableArray();
		console("[INFO] Started UDP backend #{$this->id}");
		
		$this->wait();
		$write = null;
		$except = null;
		$action = 0;
		while($this->stop == false){
			$doAction = false;
			foreach($this->sockets as $srcport => $socket){
				if(($len = @socket_recvfrom($socket, $buf, 9216, 0, $source, $port)) > 0){
					$packet = new StackablePacket($buf, $source, $port, $len);
					$packet->srcaddress = $this->address;
					$packet->srcport = $srcport;
					$this->manager->processBackendPacket($packet);
					unset($packet);
					$doAction = true;
				}
				if($this->hasPackets[$srcport] == true){
					foreach($this->queue[$srcport] as $i => $packet){
						unset($this->queue[$srcport][$i]);
						@socket_sendto($socket, $packet->buffer, $packet->len, 0, $packet->dstaddres, $packet->dstport);
						unset($packet);
					}
					if(count($this->queue[$srcport]) == 0){
						$this->hasPackets[$srcport] = false;
					}
					$doAction = true;
				}
			}

			if($doAction == false){
				if($action < 50){
					++$action;				
				}
				usleep(min(200000, $action * $action * 10));
			}else{
				$action = 0;
			}
		}
	}
}