<?php

namespace CloudService;

require "detectionAgent.php";
require "thread.php";

use CloudService\cLogger;


class RAIDA {

	var $detectionAgents;

	const RAIDA_NUM = 25;

	public function __construct($initFile = RAIDA_INIT_FILE) {
		echo "goRAIDA\n";

		$this->dir = __DIR__;
		$this->initFile = $initFile;
		$this->raida = [];

	}


	public function pownStack($stack, $progressCallBack) {
		if (!$this->initRAIDA())
			return false;
	}

	public function getCMD($cmd, $idx, $args = []) {

		$url = $this->raida[$idx];
		$args = join(" ", $args);

		$cmd = "/usr/bin/php -f " . $this->dir . "/asynctask.php $url $idx $cmd $args";

		return $cmd;
	}

	public function initRAIDA() {
		$list = file_get_contents($this->initFile);
		if (!$list) {
			cLogger::error("Failed to get list");
			return false;
		}

		$list = @json_decode($list);
                $jsonLastError = json_last_error();
		if ($jsonLastError !== JSON_ERROR_NONE) {
                        cLogger::error("RAIDA: Failed to parse json: " . $jsonLastError);
                        return false;
                }

		if (!isset($list->networks)) {
			cLogger::error("Invalid JSON");
			return false;
		}

		foreach ($list->networks as $item) {
			$nn = $item->nn;
			$raida = $item->raida;

			$this->raida[$nn] = [];
			foreach ($raida as $item) {
				$idx = $item->raida_index;
				$urls = $item->urls[0];

				$url = $urls->url;
				$port = $urls->port;

				// Omit port for now
				//$this->raida[$idx] = new DetectionAgent($idx, $url);
				$this->raida[$idx] = $url;
			}
		}
	
		$te = new TaskExecutor([$this,"cb"], self::RAIDA_NUM);
		foreach ($this->raida as $idx => $raida) {
			$cmd = $this->getCMD('echo', $idx);

			$te->executeAsync($cmd);
			echo "go\n";
		}
		
		$te->waitForAllTerminal();

		$cmd = $this->getCMD('echo', 0);
		echo "x=$cmd\n";exit;

		$te->executeWaitTerminal("/bin/ls");
		$te->executeWaitTerminal("/bin/pwd");
		return;

		$failed = 0;
		foreach ($this->raida as $idx => $raida) {
		//	$raida->fixStatus();

			if ($raida->getStatus() != RAIDA_STATUS_READY)
				$failed++;

			if ($failed > MAX_FAILED_RAIDAS) {
				cLogger::error("Too many raida servers are unavailable. Giving up");
				return false;
			}
		}



		echo "POWN stack\n";
	}

	public function cb($result, $error) {
		echo "x=$error\n";
		print_r($result);
		return;
		$rv = @json_decode($rv);
                $jsonLastError = json_last_error();
                if ($jsonLastError != JSON_ERROR_NONE) {
                        cLogger::debug("Request failed: $jsonLastError");
                        return null;
                }


		print_r($result);
		echo "cb\n";
	}

	public function runAgent($idx) {

	}

}

?>
