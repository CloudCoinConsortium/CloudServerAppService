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
use CloudService\Dispatcher;
use Workerman\Events\EventInterface;

mSystem::init(__DIR__ );


$wsWorker = new Worker("websocket://127.0.0.1:" . WS_PORT);

function fff($d) {
	echo "xxxxx=$d\n";

}

$dispatcherPid = pcntl_fork();
if ($dispatcherPid > 0) {
	$dispatcher = new Dispatcher();
	$dispatcher->run();
	exit(1);
}

$wsWorker->onWorkerStart = function($worker) {
	echo "start " . getmypid() . "\n";
	
};

$wsWorker->count = WORKERS_NUM;
$wsWorker->onConnect = function($connection) {
	$connection->wscore = new Core($connection);
};

$wsWorker->onMessage = function($connection, $data) {
	echo "m=".getmypid()."\n";
	$connection->wscore->handleMessage($data);
};

$wsWorker->onClose = function($connection) {
    echo "Connection closed\n";
    $connection->wscore->disconnect();
};

echo "xx=".getmypid()."\n";

Worker::runAll();
