<?php

/*
__PocketMine Plugin__
name=RubberBand
description=A collection of tools so development for PocketMine-MP is easier
version=1.0dev
author=shoghicp
class=RubberBand
apiversion=10,11
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
	private $server;
	public function __construct(ServerAPI $api, $server = false){
		$this->server = ServerAPI::request();
		RubberBandAPI::setInstance($this);
	}
	
	public function init(){
	}

	public function __destruct(){
	}
}

class RubberBandAPI{
	protected static $instance = false;

	public static function setInstance(RubberBand $instance){
		self::$instance = $instance;
	}

}