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

class UDPSocket{
	private $sock, $connected = false;
	
	public function __construct($address = "0.0.0.0", $port = 19132){
		$this->sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if(socket_bind($this->sock, $address, $port) === true){
			socket_set_option($this->sock, SOL_SOCKET, SO_REUSEADDR, 0);
			socket_set_option($this->sock, SOL_SOCKET, SO_SNDBUF, 65535);
			socket_set_option($this->sock, SOL_SOCKET, SO_RCVBUF, 65535);
			socket_set_nonblock($this->sock);
			$this->connected = true;
		}else{
			$this->connected = false;
			console("[ERROR] Couldn't start UDP socket on $address:$port");
		}
	}
	
	public function isConnected(){
		return $this->connected === true;
	}
	
	public function close($error = 125){
		$this->connected = false;
		return @socket_close($this->sock);
	}
	
	public function read(&$buf, &$source, &$srcport){
		if($this->connected === false){
			return false;
		}
		return @socket_recvfrom($this->sock, $buf, 9216, 0, $source, $srcport);
	}

	public function write($buf, $dest, $dstport){
		if($this->connected === false){
			return false;
		}
		return @socket_sendto($this->sock, $buf, strlen($buf), 0, $dest, $dstport);
	}

}