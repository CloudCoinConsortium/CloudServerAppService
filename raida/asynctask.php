<?php
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
require __DIR__ . '/../msystem.php';

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

$postData = false;
if (count($argv) > 0) {
	$postFile = array_shift($argv);
	$postData = @file_get_contents($postFile);
	if (!$postData)
		die('Cant get postdata');
} 

$arg = $argv;




$r = new DetectionAgent($idx, $url, true);
if ($postData)
	$r->setData($postData);
echo $r->$cmd($arg) ;
?>
