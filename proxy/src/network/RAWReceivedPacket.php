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

class RAWReceivedPacket extends Stackable{

	public $buffer, $source, $port;
	public function __construct($buffer, $source, $port){
		$this->buffer = $buffer;
		$this->source = $source;
		$this->port = $port;
	}
	
	private function deleteReference(){
		foreach($this->worker->packets as $index => $packet){
			if($packet === $this){
				unset($this->worker->packets[$index]);
			}
		}
	}
	
	public function run(){
		echo $this->worker->getThreadId()." => {$this->source}:{$this->$port}: ".bin2hex($this->buffer).PHP_EOL;
		
		$this->deleteReference();
	}

}