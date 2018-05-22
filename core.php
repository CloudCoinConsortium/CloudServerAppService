<?php


namespace CloudService;

use Workerman\Worker;
//use Workerman\Events;
use CloudService\Words;
use CloudService\Mboard;
use CloudService\cLogger;

class Core {
	var $socket;
	var $childs;
	var $masterPid;

	const PACKET_TYPE_INIT = 1;
	const PACKET_TYPE_WORD = 2;
	const PACKET_TYPE_COINS = 3;
	const PACKET_TYPE_PROGRESS = 4;
	const PACKET_TYPE_DONE = 5;
	const PACKET_TYPE_GET_WORD = 50;

	public static function &getInstance() {
                static $instance;

                if (!is_object($instance)) {
                        $instance = new Core();
			$instance->words = [];
                }

                return $instance;
        }


	public function connect($connection) {
		$fd = @stream_socket_client('unix://' . DISPATCHER_SOCKET, $errno, $errstring);
		if (!$fd) {
			cLogger::error("Failed to connect to monitor: $errno, $errstring");
			return false;
		}

		$connection->myFd = $fd;

		        return true;

		$eventLoop = $connection->worker->getEventLoop();
//		$eventLoop->add($fd, EventInterface::EV_READ, "fff");

	//	$word = Words::getWord();

		
	//	echo "Connect\n";
	}

	public function handleMessage($connection, $data) {
		$packet = @json_decode($data);
		$jsonLastError = json_last_error();
		if ($jsonLastError !== JSON_ERROR_NONE) {
			cLogger::error("Failed to parse json: " . $jsonLastError);
			$this->sendError($connection, "Invalid data");
			return false;
		}

		if (!isset($packet->type)) {
			$this->sendError($connection, "Type not specified");
			return false;
		}

		$type = $packet->type;
		switch ($type) {
			case self::PACKET_TYPE_INIT:
				$word = Words::getWord();
				if (!$this->sendWordToMonitor($connection, $word)) {
					$this->sendError($connection, "Write error");
					return false;
				}
				
				$this->sendReply($connection, self::PACKET_TYPE_WORD, $word);
				return true;
			case self::PACKET_TYPE_COINS:
				$stack = $packet->stack;
				$word = $packet->word;


				echo "w=$word\n";
//				$receiverSocket = $this->getWordSocket($word);
				$receiverSocketInode = $this->getWordFromMonitor($connection, $word);
				if ($receiverSocketInode === "N") {
					$this->sendError($connection, "Invalid recipient");
					return false;
				}

				/*if ($word == $this->word) {
					$this->sendError($connection, "You can't send coins to yourself");
					return false;
				}
				*/

					
			//	print_r($data);
				$this->sendReply($connection, self::PACKET_TYPE_PROGRESS, 0);
				sleep(1);
				$this->sendReply($connection, self::PACKET_TYPE_PROGRESS, 15);
				sleep(1);
				$this->sendReply($connection, self::PACKET_TYPE_PROGRESS, 35);
				sleep(1);
				$this->sendReply($connection, self::PACKET_TYPE_PROGRESS, 50);
				sleep(1);
				$this->sendReply($connection, self::PACKET_TYPE_PROGRESS, 80);
				sleep(1);
				$this->sendReply($connection, self::PACKET_TYPE_PROGRESS, 100);
				sleep(1);

				

				$this->sendReply($connection, self::PACKET_TYPE_DONE, "");



				return true;

			default:
				$this->sendError($connection, "Invalid packet type");
				return false;
		}

		print_r($packet);
	}

	public function getWordFromMonitor($connection, $word) {
		$data = @json_encode([
			"type" => self::PACKET_TYPE_GET_WORD,
			"data" => $word
		]);

		if (!fwrite($connection->myFd, $data)) {
			cLogger::error("Failed to send data to monitor");
			return false;
		}
		
		$data = fread($connection->myFd, 4096);
		if (!$data) {
			cLogger::error("Failed to get data from monitor");
			return false;
		}

		return $data;
	}


	public function sendWordToMonitor($connection, $word) {
		$data = @json_encode([
			"type" => self::PACKET_TYPE_WORD,
			"data" => $word
		]);

		if (!fwrite($connection->myFd, $data)) {
			cLogger::error("Failed to send data to monitor");
			return false;
		}

		return true;
	}

	public function sendReply($connection, $type, $data) {
		$data = @json_encode([
			"result" => "success",
			"type" => $type,
			"data" => $data
		]);

		print_r($data);

		$connection->send($data);
	}

	public function sendError($connection, $msg) {
		$data = @json_encode([
			"result" => "error",
			"message" => $msg
		]);

		$connection->send($data);
	}

	public function fatalError($error) {
		cLogger::error($error);

		$masterPid = $this->getMasterPid();
		if ($masterPid)
			posix_kill($masterPid, SIGTERM);
		exit(1);
	}

	public function getMasterPid() {
		$backtrace        = debug_backtrace();
		$startFile = $backtrace[count($backtrace) - 1]['file'];

	        $unique_prefix = str_replace('/', '_', $startFile);

            	$pidFile = __DIR__ . "/vendor/workerman/$unique_prefix.pid";

		return @file_get_contents($pidFile);
	}

	public function initDispatcher() {
		@unlink(DISPATCHER_SOCKET);
		$this->socket = stream_socket_server('unix://' . DISPATCHER_SOCKET, $errno, $errstring);
		if (!$this->socket) 
			$this->fatalError("Failed to created server socket");

		$this->ev = new \Workerman\Events\Select();

		$this->ev->add($this->socket, \Workerman\Events\EventInterface::EV_READ, [$this, "readEvent"]);

		return;		

		cLogger::debug("go2");
		$accepted = 0;
		while ($conn = @stream_socket_accept($this->socket)) {
			cLogger::debug("Connected " . $accepted);

			//stream_set_blocking($conn, true);
			$this->ev->add($conn, \Workerman\Events\EventInterface::EV_READ, [$this, "readEvent"]);

		//	$accepted++;
		//	if ($accepted == WORKERS_NUM)
		//		break;

	//		fwrite($conn, 'The local time is ' . date('n/j/Y g:i a') . "\n");
	//		fclose($conn);
		}

		cLogger::debug("Started");
	}

	public function readEvent($socket) {
		echo "EVENT\n";
//		$recv = fread($socket, 100);

		if ($socket == $this->socket) {
			$conn = @stream_socket_accept($this->socket);
			cLogger::debug("Connected");

                        stream_set_blocking($conn, true);
                        $this->ev->add($conn, \Workerman\Events\EventInterface::EV_READ, [$this, "readEvent"]);

			return true;
		}


		$recv = stream_socket_recvfrom($socket, DATA_MAX, 0, $addr);
		if ($recv === "") {
			fclose($socket);
			return true;
		}

		$packet = @json_decode($recv);
		$jsonLastError = json_last_error();
		if ($jsonLastError !== JSON_ERROR_NONE) {
			cLogger::error("Failed to parse json: " . $jsonLastError);
			return false;
		}

		switch ($packet->type) {
			case self::PACKET_TYPE_WORD:
				$word = $packet->data;
				$this->setWord($word, $socket);
				return true;
			case self::PACKET_TYPE_GET_WORD:
				$word = $packet->data;
				$rSocket = $this->getWordSocket($word);
				echo "zzzzzzz will write\n";

				if (!$rSocket) {
					stream_socket_sendto($socket, "N");
				} else {
					$rSocket = fstat($rSocket);
					stream_socket_sendto($socket, $rSocket['ino']);
				}

				return true;
			
			default:
				cLogger::error("Invalid packet to monitor: " . $packet->type);
				return false;
		}

		print_r($packet);

#		$recv = stream_socket_recvfrom($socket, 65535, 0, $remote_address);

		cLogger::debug("Event sssss rr=" . $recv . " s=".print_r($socket,true));

		return true;
	}
	
	public function getWordSocket($word) {
		echo "GET NOW $word\n";
		print_r($this->words);
		if (!isset($this->words[$word]))
			return false;
		
		$word = $this->words[$word];

		$now = time();
		if ($now - $word['time'] > WORD_LIFETIME) {
			unset($this->words[$word]);
			return false;
		}

		return $word['socket'];
	}

	public function setWord($word, $socket) {

		foreach ($this->words as $_word => $item) {
			if ($item['socket'] == $socket) {
				unset($this->words[$_word]);
			}
		}

		$this->words[$word] = [
			"socket" => $socket,
			"time" => time()
		];

		echo "SET NOW\n";
		print_r($this->words);
	}

	public function runLoop() {

		$this->ev->loop();

		$this->fatalError("Finished");
	}


}

