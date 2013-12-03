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

class RubberBandRC4{
	private $S;

	public function __construct($key, $drop = 768){
		for($i = 0; $i < 256; ++$i){
			$this->S[$i] = $i;
		}
		
		$j = 0;
		for($i = 0; $i < 256; ++$i){
			$j = ($j + $this->S[$i] + ord($key{$i % strlen($key)})) & 0xFF;
			$this->S[$i] ^= $this->S[$j];
			$this->S[$j] ^= $this->S[$i];
			$this->S[$i] ^= $this->S[$j];
		}
		
		$i = $j = 0;
		for($k = 0; $k < $drop; ++$k){
			$i = ($i + 1) & 0xFF;
			$j = ($j + $this->S[$i]) & 0xFF;
			$this->S[$i] ^= $this->S[$j];
			$this->S[$j] ^= $this->S[$i];
			$this->S[$i] ^= $this->S[$j];
		}
	}
	
	public function encrypt($text, $IV = false){
		$IV = $IV === false ? substr(md5(microtime().mt_rand(), true), mt_rand(0, 7), 8):substr($IV, 0, 8);
		$text = $IV . $text;
		$len = strlen($text);
		$i = $j = 0;
		$S = $this->S;
		$text0 = "\x00";
		for($k = 0; $k < $len; ++$k){
			$i = ($i + 1) & 0xFF;
			$j = ($j + $S[$i]) & 0xFF;
			$S[$i] ^= $S[$j];
			$S[$j] ^= $S[$i];
			$S[$i] ^= $S[$j];
			$K = chr($S[($S[$i] + $S[$j]) & 0xFF]) ^ $text0;
			$text{$k} = $text{$k} ^ $K;
			$text0 = $text{$k};
		}
		return $text;
	}
	
	public function decrypt($text){
		$len = strlen($text);
		$i = $j = 0;
		$S = $this->S;
		$text0 = "\x00";
		for($k = 0; $k < $len; ++$k){
			$i = ($i + 1) & 0xFF;
			$j = ($j + $S[$i]) & 0xFF;
			$S[$i] ^= $S[$j];
			$S[$j] ^= $S[$i];
			$S[$i] ^= $S[$j];
			$K = chr($S[($S[$i] + $S[$j]) & 0xFF]) ^ $text0;
			$text0 = $text{$k};
			$text{$k} = $text{$k} ^ $K;
		}
		return substr($text, 8);
	}

}