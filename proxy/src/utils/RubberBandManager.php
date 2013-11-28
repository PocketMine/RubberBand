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
	private $threads;
	private $apiKey;
	private $frontendThread;
	private $frontendWorkers;
	public $data = array();
	
	public function __construct($address, $port, $threads, $apiKey){
		$this->data = array($address, $port, $threads, $apiKey);
		self::$instance = $this;
	}
	
	private function getIdentifier($address, $port){
		return crc32(hash("sha1", $address.":".$port."|".$this->apiKey, true));
	}
	
	public function addFrontendWorker(){
		$k = count($this->frontendWorkers);
		$this->frontendWorkers[$k] = new RubberBandReceiveWorker;
		while(!$this->frontendWorkers[$k]->isStarted()){
			usleep(1);
		}
		$this->frontendThread->addWorker($this->frontendWorkers[$k]);
		console("[DEBUG] Added frontend Worker", true, true, 2);
		return true;
	}

	public function run(){
		$this->stop = false;
		$this->address = $this->data[0];
		$this->port = $this->data[1];
		$this->threads = $this->data[2];
		$this->apiKey = $this->data[3];

		$this->frontendThread = new RubberBandFrontend($this->address, $this->port);
		while(!$this->frontendThread->isRunning()){
			usleep(1);
		}
		
		while(!$this->frontendThread->isWaiting() and !$this->frontendThread->isTerminated()){
			usleep(1);
		}
		
		if($this->frontendThread->isTerminated()){
			return 1;
		}

		$this->frontendWorkers = new StackableArray();
		for($k = 0; $k < $this->threads; ++$k){
			$this->addFrontendWorker();
		}

		$this->frontendThread->notify();

		while($this->stop == false){
			sleep(1);
		}
		return 0;
	}
	
}