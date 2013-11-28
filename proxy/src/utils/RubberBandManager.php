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
	private $stop;
	private $address;
	private $port;
	private $threads;
	private $apiKey;
	private $socket;
	private $frontendThreads;
	private $frontendWorkers;
	public $data = array();
	public function __construct($address, $port, $threads, $apiKey){
		$this->data = array($address, $port, $threads, $apiKey);
	}

	public function run(){
		$this->stop = false;
		$this->address = $this->data[0];
		$this->port = $this->data[1];
		$this->threads = $this->data[2];
		$this->apiKey = $this->data[3];
		
		$this->socket = new UDPSocket($this->address, $this->port);
		
		if(!$this->socket->isConnected()){
			return 1;
		}
		
		$this->frontendThreads = array();

		for($k = 0; $k < $this->threads; ++$k){
			
		}

		while($this->stop === false){

		}
		return 0;
	}
	
}