<?php


namespace CloudService;

use Workerman\Worker;
//use Workerman\Events;
use CloudService\Words;
use CloudService\Mboard;
use CloudService\cLogger;
use Workerman\Events\EventInterface;

class Core {
	var $connection;
	var $dispatcherFd;

	public function __construct($connection) {
		$this->connection = $connection;
		$this->connect();
	}

	public function connect() {
		$fd = @stream_socket_client('unix://' . DISPATCHER_SOCKET, $errno, $errstring);
		if (!$fd) {
			cLogger::error("Failed to connect to monitor: $errno, $errstring");
			return false;
		}

		cLogger::debug(getmypid() . " connected with $fd");
		$this->dispatcherFd = $fd;

		$eventLoop = $this->connection->worker->getEventLoop();
		$eventLoop->add($this->dispatcherFd, EventInterface::EV_READ, [$this, "recvFromMonitor"]);

		return true;
	}

	public function disconnect() {
		@fclose($this->dispatcherFd);	
	}

	public function recvFromMonitor($socket) {
		cLogger::debug("from monitor");

		$recv = stream_socket_recvfrom($socket, DATA_MAX, 0, $addr);
		if (!$recv) {
			cLogger::error("Failed to recv from monitor");
			fclose($socket);
			return false;
		}

		cLogger::debug(print_r($recv,true));
		$packets = preg_split("/}{/", $recv);

		$i = 0;
		foreach ($packets as $packet) {
			if (!($i % 2))
				$packet .= "}";
			else
				$packet = "{" . $packet;

			$packet = @json_decode($packet);
        	        $jsonLastError = json_last_error();
                	if ($jsonLastError !== JSON_ERROR_NONE) {
	                        cLogger::error("RecvFrom. Failed to parse json $packet for socket $socket: " . $jsonLastError);
        	                return false;
                	}

			switch ($packet->type) {
				case PACKET_TYPE_PING:
					if ($packet->hash) {
						$hash = $packet->hash;
						cLogger::debug("received hash $hash");

						$this->sendReply(PACKET_TYPE_HASH, $hash);
					}
					break;
			}

			$i++;	
	
		}





	}

	public function progressReport($progress) {
		$this->sendReply(PACKET_TYPE_PROGRESS, $progress);
	}

	public function handleMessage($data) {
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
			case PACKET_TYPE_INIT:
				$word = Words::getWord();
				if (!$this->sendWordToMonitor($word)) {
					$this->sendError("Write error");
					return false;
				}
				
				$this->sendReply(PACKET_TYPE_WORD, $word);
				return true;
			case PACKET_TYPE_COINS:
				$stack = $packet->stack;
				$word = $packet->word;

				$rv = $this->requestRecipient($word);
				if (!$rv) {
					$this->sendError("Failed to contact recipient");
					return false;
				}

				$raida = new RAIDA();
				$rv = $raida->pownStack($stack, [$this, "progressReport"]);
				if ($rv === false) {
					$this->sendError($raida->error);
					return false;
				}

				$this->sendToRecipient($word, $rv);

				$this->sendReply(PACKET_TYPE_DONE, "");


				return true;

			default:
				$this->sendError($connection, "Invalid packet type");
				return false;
		}

		print_r($packet);
	}

	public function sendToRecipient($word, $hash) {
		$data = @json_encode([
			"type" => PACKET_TYPE_REQUEST_RECIPIENT,
			"data" => $word,
			"hash" => $hash
		]);

		if (!fwrite($this->dispatcherFd, $data)) {
			cLogger::error("Failed to send data to monitor");
			return false;
		}

		$rv = fread($this->dispatcherFd, 1);
		if (!$rv || $rv == REPLY_NOTOK) {
			cLogger::error("Failed to ping recipient");
			return false;
		}

		return true;
	}

	public function requestRecipient($word) {
		$data = @json_encode([
			"type" => PACKET_TYPE_REQUEST_RECIPIENT,
			"data" => $word
		]);

		if (!fwrite($this->dispatcherFd, $data)) {
			cLogger::error("Failed to send data to monitor");
			return false;
		}

		$rv = fread($this->dispatcherFd, 1);
		if (!$rv || $rv == REPLY_NOTOK) {
			cLogger::error("Failed to ping recipient");
			return false;
		}

		return true;
	}

	public function getWordFromMonitor($word) {
		$data = @json_encode([
			"type" => PACKET_TYPE_GET_WORD,
			"data" => $word
		]);

		if (!fwrite($this->dispatcherFd, $data)) {
			cLogger::error("Failed to send data to monitor");
			return false;
		}
		
		$data = fread($this->dispatcherFd, 4096);
		if (!$data) {
			cLogger::error("Failed to get data from monitor");
			return false;
		}

		return $data;
	}


	public function sendWordToMonitor($word) {
		$data = @json_encode([
			"type" => PACKET_TYPE_WORD,
			"data" => $word
		]);

		if (!fwrite($this->dispatcherFd, $data)) {
			cLogger::error("Failed to send data to monitor");
			return false;
		}

		return true;
	}

	public function sendReply($type, $data) {
		$data = @json_encode([
			"result" => "success",
			"type" => $type,
			"data" => $data
		]);

		$this->connection->send($data);
	}

	public function sendError($msg) {
		$data = @json_encode([
			"result" => "error",
			"message" => $msg
		]);

		$this->connection->send($data);
	}
}

