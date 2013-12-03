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

class RubberBandProxy{
	private $config, $address, $port, $apiKey, $backendThreads = 1;
	private $manager;

	public function __construct(Config $config){
		$this->config = $config;
		
		$this->apiKey = $this->config->get("api-key");
		if($this->apiKey == false or $this->apiKey == "YOUR_API_KEY"){
			console("[ERROR] API key not set. RubberBand won't work without setting it on the config.yml");
			return;
		}
		
		$this->backendThreads = $this->config->get("backend-threads");
		if(!is_int($this->backendThreads) or $this->backendThreads < 1){
			$this->config->set("backend-threads", 1);
			$this->backendThreads = 1;
		}
		
		$this->address = $this->config->get("frontend-address");
		if($this->address === false){
			console("[ERROR] Frontend Address not set. Set it on the config.yml");
			return;
		}
		
		$this->port = $this->config->get("frontend-port");
		if($this->port === false){
			console("[ERROR] Frontend Port not set. Set it on the config.yml");
			return;
		}
		
		console("[INFO] Starting RubberBand Proxy ".RUBBERBAND_VERSION." on ".$this->address.":".$this->port);
		$this->manager = new RubberBandManager($this->address, $this->port, $this->backendThreads, $this->apiKey);
		$this->manager->start();
	}
}