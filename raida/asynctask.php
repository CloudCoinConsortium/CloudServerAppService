<?php
require_once __DIR__ . '/../vendor/autoload.php';
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
use CloudService\DetectionAgent;

date_default_timezone_set('UTC');



if (count($argv) < 3)
	die("Invalid invocation\n");

array_shift($argv);

$url = array_shift($argv);
$idx = array_shift($argv);
$cmd = array_shift($argv);
$arg = $argv;



$r = new DetectionAgent($idx, $url, true);
echo $r->$cmd($arg) ;
?>
