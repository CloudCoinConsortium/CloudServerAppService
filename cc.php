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



$h = $_GET['h'];
$h = @base64_decode($h);
if (!$h)
	die('Invalid hash1');

if (!preg_match("/^\d{4}-\d{2}-\d{2}\/[a-f0-9]+\.(powned|change)\.\d+\.\d+\.stack$/", $h))
	die('Invalid hash2');


$file = __DIR__ . "/" . COIN_STORAGE_DIR . "/$h";
if (!file_exists($file))
	die('File does not exist');

$mtime = filemtime($file);
if (time() - $mtime > MAX_FILE_TIME)
	die('File is outdated');


header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.basename($file).'"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file));
readfile($file);

