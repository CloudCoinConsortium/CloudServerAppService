<?php


namespace CloudService;

use Workerman\Worker;
//use Workerman\Events;
use CloudService\Words;
use CloudService\Mboard;
use CloudService\cLogger;
use Workerman\Events\EventInterface;

use CloudBank\CloudBank;
use CloudBank\Stack;
use CloudBank\CloudBankException;

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

				$stackId = md5($stack);

				$p = 5;
				$this->progressReport($p);
				try {
					$stackObj = new Stack($stack);
					$total = $stackObj->getTotal();

					$p += 5;
					$this->progressReport($p);

					$cBank = new CloudBank([
						"url" => CLOUDBANK_URL,
						"privateKey" => CLOUDBANK_KEY,
						"account" => CLOUDBANK_ACCOUNT,
						"debug" => true,
						"timeout" => 60
					]);

					// Check connection
					$version = $cBank->getVersion();

					$response = $cBank->echoRAIDA();
					if ($response->status != "ready") {
						$this->sendError("RAIDA is not ready");
						return false;
					}

					$p += 5;
					$this->progressReport($p);

					cLogger::debug("Depositing sack $stackId. Total $total");

					$this->saveCoin("init", $stackId, $stack);

					$response = $cBank->depositStack($stack);
					if ($response->isError()) {
						$this->sendError("Failed to import coins. Please check them");
						return false;
					}

					$receiptNumber = $response->receipt;
					cLogger::debug("receipt $receiptNumber");

					$p += 35;
					$this->progressReport($p);
					$receiptResonse = $cBank->getReceipt($receiptNumber);
					if (!$receiptResonse->isValid()) {
						$receipt = @json_encode($receiptResonse->receipt);
						$receipt = "{ 'cloudcoin' : $receipt }";
						$this->saveCoin("counterfeit", $stackId, $receipt);
						$this->sendError("The coins are counterfeit");
						return ;
					}

					$p += 5;
					$this->progressReport($p);
					$withdrawRespose = $cBank->withdrawStack($total);
					$newStack = $withdrawRespose->getStack();
					$hash = $this->saveCoin("powned", $stackId, $newStack);

				} catch (CloudBankException $e) {
					$this->saveCoin("exception", $stackId, $stack);
					$this->sendError("Failed to process stack file: " . $e->getMessage());
					return false;
				}

				$this->sendToRecipient($word, $hash);
				$this->sendReply(PACKET_TYPE_DONE, "");
				
				return true;
			default:
				$this->sendError($connection, "Invalid packet type");
				return false;
		}

		return true;
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

	public function saveCoin($type, $stackId, $stack) {
                $dateDir = date("Y-m-d");
                $pDir = __DIR__ .  "/" . COIN_STORAGE_DIR . "/$dateDir";
                if (!is_dir($pDir))
                        @mkdir($pDir);

                $rand = mt_rand(10000, 655000);
		$fname = "$stackId.$type." . time() .  ".$rand.stack";

                $path = "$pDir/$fname";

                cLogger::debug("Saving stack $path");
                file_put_contents($path, $stack);

                $hash = base64_encode("$dateDir/$fname");

                return $hash;
	}

}

