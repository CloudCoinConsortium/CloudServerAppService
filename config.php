<?php


define('WS_PORT', 2348);


define('LOG_FILENAME', __DIR__ . '/log.txt');

define('WORDS_FILENAME', 'words5.txt');

define('WORKERS_NUM', 1);

define('DISPATCHER_SOCKET', '/tmp/ewsserver.sock');


define('DATA_MAX', 256000);

// seconds
define('WORD_LIFETIME', 600);


define('PACKET_TYPE_INIT', 1);
define('PACKET_TYPE_WORD', 2);
define('PACKET_TYPE_COINS', 3);
define('PACKET_TYPE_PROGRESS', 4);
define('PACKET_TYPE_DONE', 5);
define('PACKET_TYPE_REQUEST_RECIPIENT', 6);
define('PACKET_TYPE_OK', 7);
define('PACKET_TYPE_HASH', 8);
define('PACKET_TYPE_RECIPIENT_REPLY', 50);

define('PACKET_TYPE_GET_WORD', 51);


define('PACKET_TYPE_PING', 52);
define('PACKET_TYPE_PING_RESPONSE', 53);

define('REPLY_OK', 'Y');
define('REPLY_NOTOK', 'N');

define('RAIDA_INIT_FILE', 'https://www.cloudcoin.global/servers.html');

define('STORAGE_DIR', 'storage');

define('RAIDA_STATUS_NOTREADY', 1);
define('RAIDA_STATUS_READY', 2);

define('SOCKET_TIMEOUT', 10);
define('JSON_CONTENT_TYPE', 'application/json');

define('MAX_FAILED_RAIDAS', 3);

define('COIN_STORAGE_DIR', 'storage');


define('RAIDA_COIN_RESULT_COUNTERFEIT', 'fail');
define('RAIDA_COIN_RESULT_VALID', 'pass');

define('MAX_FAILED', 5);

define('MAX_FILE_TIME', 600);


define('CLOUDBANK_URL', 'https://bank.cloudcoin.global/service');
define('CLOUDBANK_ACCOUNT', 'trustedtransfer');
define('CLOUDBANK_KEY', '90FDC006BDDF4473B619E132DA10A81B');


?>
