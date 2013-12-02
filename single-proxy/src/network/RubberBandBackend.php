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
	public $sockets, $manager, $socketCount;
	private $stop;
	
	public function __construct(RubberbandManager $manager){
		$this->manager = $manager;
		$this->socketCount = 0;
		$this->start();
	}
	
	public function addSocket(){
		if(($this->sockets[++$this->socketCount] = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) !== false){
			return $this->socketCount;
		}
		return false;
	}
	
	public function sendPacket($socketID, StackablePacket $packet){
		return @socket_sendto($this->sockets[$socketID], $packet->buffer, $packet->len, 0, $packet->dstaddres, $packet->dstport);
	}
	
	public function stop(){
		$this->stop = true;
	}
	
	public function run(){
		$this->stop = false;
		$this->clients = new StackableArray();
		console("[INFO] Started UDP backend");
		
		$this->wait();
		
		while($this->stop == false){
			/*$read = clone $this->sockets;
			$write = null;
			$except = null;
			if(socket_select($read, $write, $except, null) > 0){
				var_dump($read);
			}*/
			usleep(1);
		}
	}
}