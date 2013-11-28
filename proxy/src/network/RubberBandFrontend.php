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

class RubberBandFrontend extends Thread{
	public $socket;
	private $workers;
	public function __construct(UDPSocket $socket){
		$this->socket = $socket;
		$this->start();
	}
	
	public function addWorker(RubberBandWorker $worker, $identifier){
		$this->workers[$identifier] = $worker;
	}
	
	public function removeWorker($identifier){
		unset($this->workers[$identifier]);
	}

	public function run(){
		$this->workers = new StackableArray();
	
	}
	
}