<?php

declare(ticks = 1);

require_once __DIR__ . '/vendor/autoload.php';

require 'config.php';
require "msystem.php";

use Workerman\Worker;
use CloudService\mSystem;
use CloudService\Words;
use CloudService\Mboard;
use CloudService\Core;
use CloudService\cLogger;
use Workerman\Events\EventInterface;

mSystem::init(__DIR__ );


$wsWorker = new Worker("websocket://127.0.0.1:" . WS_PORT);

function fff($d) {
	echo "xxxxx=$d\n";

}

$dispatcherPid = pcntl_fork();
if ($dispatcherPid > 0) {
	Core::getInstance()->initDispatcher();
	Core::getInstance()->runLoop();
	exit(1);
}

$wsWorker->onWorkerStart = function($worker) {
	echo "OLALAA " . getmypid() . "\n";
	
};

$wsWorker->count = WORKERS_NUM;
$wsWorker->onConnect = function($connection) {
	Core::getInstance()->connect($connection);
};

$wsWorker->onMessage = function($connection, $data) {
	echo "m=".getmypid()."\n";
	Core::getInstance()->handleMessage($connection, $data);
};

$wsWorker->onClose = function($connection) {
    echo "Connection closed\n";
};

echo "xx=".getmypid()."\n";

Worker::runAll();
