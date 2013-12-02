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


if(!function_exists("cli_set_process_title")){
	function cli_set_process_title($title){
		if(ENABLE_ANSI === true){
			echo "\x1b]0;".$title."\x07";
			return true;
		}else{
			return false;
		}
	}
}

function dummy(){
	
}

function safe_var_dump($var, $cnt = 0){
	switch(true){
		case is_array($var):
			echo str_repeat("  ", $cnt)."array(".count($var).") {".PHP_EOL;
			foreach($var as $key => $value){
				echo str_repeat("  ", $cnt + 1)."[".(is_integer($key) ? $key:'"'.$key.'"')."]=>".PHP_EOL;
				safe_var_dump($value, $cnt + 1);
			}
			echo str_repeat("  ", $cnt)."}".PHP_EOL;
			break;
		case is_integer($var):
			echo str_repeat("  ", $cnt)."int(".$var.")".PHP_EOL;
			break;
		case is_float($var):
			echo str_repeat("  ", $cnt)."float(".$var.")".PHP_EOL;
			break;
		case is_bool($var):
			echo str_repeat("  ", $cnt)."bool(".($var === true ? "true":"false").")".PHP_EOL;
			break;
		case is_string($var):
			echo str_repeat("  ", $cnt)."string(".strlen($var).") \"$var\"".PHP_EOL;
			break;
		case is_resource($var):
			echo str_repeat("  ", $cnt)."resource() of type (".get_resource_type($var).")".PHP_EOL;
			break;
		case is_object($var):
			echo str_repeat("  ", $cnt)."object(".get_class($var).")".PHP_EOL;
			break;
		case is_null($var):
			echo str_repeat("  ", $cnt)."NULL".PHP_EOL;
			break;
	}
}

function kill($pid){
	switch(Utils::getOS()){
		case "win":
			exec("taskkill.exe /F /PID ".((int) $pid)." > NUL");
			break;
		case "mac":
		case "linux":
		default:
			exec("kill -9 ".((int) $pid)." > /dev/null 2>&1");
	}
}

function console($message, $EOL = true, $log = true, $level = 1){
	if(!defined("DEBUG") or DEBUG >= $level){
		$message .= $EOL === true ? PHP_EOL:"";
		$time = date("H:i:s") . " ";
		$message = $time . $message;
		if($log === true and (!defined("LOG") or LOG === true)){
			logg(date("Y-m-d")." ".$message, "console", false, $level);
		}
		echo $message;
	}
}

function error_handler($errno, $errstr, $errfile, $errline){
	if(error_reporting() === 0){ //@ error-control
		return false;
	}
	console("[ERROR] A level ".$errno." error happened: \"$errstr\" in \"$errfile\" at line $errline", true, true, 0);
	return true;
}

function logg($message, $name, $EOL = true, $level = 2, $close = false){
	global $fpointers;
	if((!defined("DEBUG") or DEBUG >= $level) and (!defined("LOG") or LOG === true)){
		$message .= $EOL === true ? PHP_EOL:"";
		if(!isset($fpointers)){
			$fpointers = array();
		}
		if(!isset($fpointers[$name]) or $fpointers[$name] === false){
			$fpointers[$name] = @fopen($name.".log", "ab");
		}
		@fwrite($fpointers[$name], $message);
		if($close === true){
			fclose($fpointers[$name]);
			unset($fpointers[$name]);
		}
	}
}