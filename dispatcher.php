<?php


namespace CloudService;

use Workerman\Worker;
//use Workerman\Events;
use CloudService\Words;
use CloudService\Mboard;
use CloudService\cLogger;
use Workerman\Events\EventInterface;
use Workerman\Events;

class Dispatcher {
	var $socket;
	var $childs;
	var $masterPid;

	//var $socket;

	public function __construct() {
		@unlink(DISPATCHER_SOCKET);
		$this->socket = stream_socket_server('unix://' . DISPATCHER_SOCKET, $errno, $errstring);
		if (!$this->socket) 
			$this->fatalError("Failed to created server socket");

		$this->words = [];
		$this->ev = new \Workerman\Events\Select();
		$this->ev->add($this->socket, EventInterface::EV_READ, [$this, "readEvent"]);
	}

	public function run() {
		$this->ev->loop();
                $this->fatalError("Finished");
	}

	public function readEvent($socket) {
		if ($socket == $this->socket) {
			$conn = @stream_socket_accept($this->socket);
			stream_set_blocking($conn, true);

			$this->ev->add($conn, EventInterface::EV_READ, [$this, "readEvent"]);

                        return true;
                }

                $recv = stream_socket_recvfrom($socket, DATA_MAX, 0, $addr);
                if ($recv === "") {
			echo "closing socket $socket\n";
                        $u = false;
                        foreach ($this->words as $w => $item) {
                                if ($item['socket'] == $socket) {
                                        $u = true;
                                        break;
                                }
                        }

                        if ($u) {
				echo "unset $w\n";
                                unset($this->words[$w]);
			}

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
			case PACKET_TYPE_WORD:
                                $word = $packet->data;
				echo "DISP: will set word $word for $socket\n";
                                $this->setWordSocket($word, $socket);
                                break;
                        case PACKET_TYPE_GET_WORD:
				$word = $packet->data;
				$rSocket = $this->getWordSocket($word);

				if (!$rSocket) {
					stream_socket_sendto($socket, "N");
				} else {
					$rSocket = fstat($rSocket);
					stream_socket_sendto($socket, $rSocket['ino']);
                                }

                                break;
                        case PACKET_TYPE_REQUEST_RECIPIENT:
				$word = $packet->data;
				$rSocket = $this->getWordSocket($word);
				if (!$rSocket) {
					stream_socket_sendto($socket, REPLY_NOTOK);
				} else {
					echo getmypid() . ": sending fd $rSocket our socket $socket [$word]\n";
					if ($this->pingNode($rSocket)) {
						stream_socket_sendto($socket, REPLY_OK);
					} else {
						stream_socket_sendto($socket, REPLY_NOTOK);

					}
					echo "ok\n";
                                }
				break;
			default:
				cLogger::error("Invalid packet to monitor: " . $packet->type);
				break;
                }


		//$this->ev->add($socket, EventInterface::EV_READ, [$this, "readEvent"]);

		return true;
	}

	public function pingNode($rSocket) {
		$data = [
			"type" => PACKET_TYPE_PING
		];

		$data = @json_encode($data);

		echo "pinging\n";
		// Mere check if we can write. Response doesn't matter
		if (!stream_socket_sendto($rSocket, $data)) {
			cLogger::error("socket $rSocket can't ping");
			return false;
		}

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

	public function setWordSocket($word, $socket) {
		foreach ($this->words as $_word => $item) {
			if ($item['socket'] == $socket) {
				unset($this->words[$_word]);
			}
		}

		$this->words[$word] = [
			"socket" => $socket,
			"time" => time()
		];

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
}

