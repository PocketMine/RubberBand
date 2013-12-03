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
	
	public function __construct(RubberbandManager $manager, $address, $id = 0){
		$this->manager = $manager;
		$this->address = $address;
		$this->id = $id;
		$this->start();
	}
	
	public function addSocket(){
		if(($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) !== false and socket_bind($socket, $this->address) !== false){
			$port = null;
			$address = null;
			socket_getsockname($socket, $address, $port);
			$this->sockets[$port] = $socket;
			return $port;
		}
		return false;
	}
	
	public function sendPacket($srcport, StackablePacket $packet){
		return @socket_sendto($this->sockets[$srcport], $packet->buffer, $packet->len, 0, $packet->dstaddres, $packet->dstport);
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
				$read = array($socket);
				if(socket_select($read, $write, $except, 0, 0) > 0){
					if(($len = @socket_recvfrom($socket, $buf, 9216, 0, $source, $port)) > 0){
						$packet = new StackablePacket($buf, $source, $port, $len);
						$packet->srcaddress = $this->address;
						$packet->srcport = $srcport;
						$this->manager->processBackendPacket($packet);
						unset($packet);
						$doAction = true;
					}
				}
			}

			if($doAction == false){
				++$action;
				usleep(min(100000, $action * $action * 10));
			}else{
				$action = 0;
			}
		}
	}
}