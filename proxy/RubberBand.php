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

define("RUBBERBAND_VERSION", "v1.0dev");
define("DEBUG", 1);

require_once("src/utils/functions.php");
require_once("src/utils/Utils.php");

set_error_handler("error_handler", E_ALL);
$errors = 0;
if(version_compare("5.4.0", PHP_VERSION) > 0){
	console("[ERROR] Use PHP >= 5.4.0", true, true, 0);
	++$errors;
}
if(php_sapi_name() !== "cli"){
	console("[ERROR] You must run RubberBand using the CLI.", true, true, 0);
	++$errors;
}
if(!extension_loaded("sockets") and @dl((PHP_SHLIB_SUFFIX === "dll" ? "php_":"") . "sockets." . PHP_SHLIB_SUFFIX) === false){
	console("[ERROR] Unable to find the Socket extension.", true, true, 0);
	++$errors;
}
if(!extension_loaded("pthreads") and @dl((PHP_SHLIB_SUFFIX === "dll" ? "php_":"") . "pthreads." . PHP_SHLIB_SUFFIX) === false){
	console("[ERROR] Unable to find the pthreads extension.", true, true, 0);
	++$errors;
}
if(!extension_loaded("curl") and @dl((PHP_SHLIB_SUFFIX === "dll" ? "php_":"") . "curl." . PHP_SHLIB_SUFFIX) === false){
	console("[ERROR] Unable to find the cURL extension.", true, true, 0);
	++$errors;
}
if(!extension_loaded("sqlite3") and @dl((PHP_SHLIB_SUFFIX === "dll" ? "php_":"") . "sqlite3." . PHP_SHLIB_SUFFIX) === false){
	console("[ERROR] Unable to find the SQLite3 extension.", true, true, 0);
	++$errors;
}
if(!extension_loaded("zlib") and @dl((PHP_SHLIB_SUFFIX === "dll" ? "php_":"") . "zlib." . PHP_SHLIB_SUFFIX) === false){
	console("[ERROR] Unable to find the Zlib extension.", true, true, 0);
	++$errors;
}

if($errors > 0){
	console("[ERROR] Please set up RubberBand environment properly.", true, true, 0);
	exit(1); //Exit with error
}


require_once("src/utils/Spyc.php");
require_once("src/utils/Config.php");
require_once("src/network/UDPSocket.php");
require_once("src/network/RubberBandFrontend.php");
require_once("src/network/RubberBandWorker.php");
require_once("src/utils/RubberBandManager.php");

require_once("src/RubberBandProxy.php");


$config = new Config("config.yml", CONFIG_YAML, array(
	"frontend-address" => "0.0.0.0",
	"frontend-port" => 19132,
	"frontend-threads" => 2,
	"api-key" => "YOUR_API_KEY",
));

$proxy = new RubberBandProxy($config);

exit(0);