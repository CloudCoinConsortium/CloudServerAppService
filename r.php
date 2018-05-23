<?php
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
use CloudService\RAIDA;

date_default_timezone_set('UTC');

function xxx($p) {
	echo "progress=$p\n";
}

$stack='{}';
$r = new RAIDA();
$r->pownStack($stack, "xxx");





?>
