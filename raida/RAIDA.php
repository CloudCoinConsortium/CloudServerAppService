<?php

namespace CloudService;

require "detectionAgent.php";
require __DIR__ . "/../thread.php";

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


	public function saveCoin($type, $stack) {
		$stack = @json_encode($stack);

		$dateDir = date("Y-m-d");
		$pDir = $this->dir . "/../" . COIN_STORAGE_DIR . "/$dateDir";
		if (!is_dir($pDir))
			@mkdir($pDir);

		$rand = mt_rand(10000, 655000);
		$path = "$pDir/" . $this->stackId . ".$type." . time().  ".$rand.stack";

		cLogger::debug("Saving stack $path");
		file_put_contents($path, $stack);

		return $path;
	}

	public function genStackId() {
		$this->stackId = mt_rand(0, 165535);
	}

	
	public function saveCoinsDB($type) {
		$coins = $this->coinsDB;

		foreach ($coins as $idx => $c) {
			unset($coins[$idx]['statuses']);
			unset($coins[$idx]['status']);
			unset($coins[$idx]['denomination']);
			unset($coins[$idx]['origans']);
		}

		$data = [
			"cloudcoin" => $coins
		];

		return $this->saveCoin($type, $data);
	}

	public function pownStack($stack, $progressCallBack) {

		$this->progress = 0;
		$this->progressCallBack = $progressCallBack;

		if (!$this->initRAIDA())
			return false;

		$this->genStackId();

		$data = @json_decode($stack);
                $jsonLastError = json_last_error();
		if ($jsonLastError !== JSON_ERROR_NONE) {
			$this->error = "Stack file is corrupted";
                        cLogger::error("RAIDA: Failed to parse json: " . $jsonLastError);
                        return false;
		}

		if (!isset($data->cloudcoin)) {
			$this->error = "Stack file is invalid";
                        cLogger::error("RAIDA: Failed to parse json: " . $jsonLastError);
                        return false;
		}

		$this->saveCoin("orig", $data);
		$data = $data->cloudcoin;

		$this->coinsDB = [];
		$this->newStackData = [
			'cloudcoin' => []
		];

		foreach ($data as $cc) {
			if (!isset($cc->sn) || !isset($cc->an) || !isset($cc->nn) || !isset($cc->ed)) {
				$this->error = "Coins are corrupted";
        	                cLogger::error("Coins are corrupted");
                	        return false;
			}
			
			if (!is_array($cc->an) || count($cc->an) != self::RAIDA_NUM) {
				$this->error = "No ans";
				cLogger::error("No ans");
				return false;
			}

			$denomination = $this->getDenomination($cc->sn);
			if (!$denomination) {
				$this->error = "Invalid SN";
				cLogger::error("Invalid SN");
				return false;
			}

			//$nns[] = "nns[]=" . $cc->nn;
			//$sns[] = "sns[]=" . $cc->sn;
			//$denominations[] = "denomination[]=" . $denomination;

			$cpans = $this->generatePans();

			//$pans[] = $cpans;
			//$ans[] = $cc->an;

			$ed = date("m-Y");
			$newcc = [
				'ans' => $cpans,
				'sn' => $cc->sn,
				'nn' => $cc->nn,
				'aoid' => $cc->aoid,
				'ed' => $ed
			];


			$this->newStackData['cloudcoin'][] = $newcc;

			$newcc['denomination'] = $denomination;
			$newcc['origans'] = $cc->an;
			$newcc['statuses'] =   [RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT,
						RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT,
						RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT,
						RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT,
						RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT,
						RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT,
						RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT,
						RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT, RAIDA_COIN_RESULT_COUNTERFEIT,
						RAIDA_COIN_RESULT_COUNTERFEIT];

			$newcc['status'] = RAIDA_COIN_RESULT_COUNTERFEIT;

			$this->coinsDB[] = $newcc;
		}

		$this->saveCoinsDB("hope");

		$nns = $allANs = $sns = $allPANs = $denominations = [];
		foreach ($this->coinsDB as $coin) {
			$nns[] = "nns[]=" . $coin['nn'];
			$sns[] = "sns[]=" . $coin['sn'];
			$denominations[] = "denomination[]=" . $coin['denomination'];

			$allANs[] = $coin['origans'];
			$allPANs[] = $coin['ans'];
		}

		$nns = join("&", $nns);
		$sns = join("&", $sns);
		$denominations = join("&", $denominations);
		

		$te = new TaskExecutor([$this,"mdcb"], self::RAIDA_NUM);
		$tmpNames = [];

		foreach ($this->raida as $idx => $raida) {
			$myans = $this->getMyANs($allANs, $idx);
			$mypans = $this->getMyPANs($allPANs, $idx);

			$myans = join("&", $myans);
			$mypans = join("&", $mypans);

			$params = join("&", [$nns, $sns, $myans, $mypans, $denominations]);

			cLogger::debug("RAIDA $idx, $params");

			$tmpNames[$idx] = "/tmp/_raida$idx";
			if (!file_put_contents($tmpNames[$idx], $params)) {
				$this->error = "Internal system error";
				cLogger::error("Failed to save file {$tmpNames[$idx]}");
				return false;
			}

			
			$cmd = $this->getCMD('multi_detect', $idx, $tmpNames[$idx]);

			$te->executeAsync($cmd, $idx);
		}
		
		$te->waitForAllTerminal();

		$allValid = true;
		$allFailed = true;
		foreach ($this->coinsDB as $idx => $coin) {
			$failed = 0;
			foreach ($coin['statuses'] as $status) {
				if ($status == RAIDA_COIN_RESULT_COUNTERFEIT)
					$failed++;
			}	

			if ($failed < MAX_FAILED) {
				$this->coinsDB[$idx]['status'] = RAIDA_COIN_RESULT_VALID;
				$allFailed = false;
			} else {
				$allValid = false;
			}

			echo "coin {$coin['sn']} failed $failed\n";
		}

		if ($allFailed) {
			$this->saveCoinsDB("counterfeit");
			$this->error = "All coins are counterfeit";
			cLogger::error("All coins are counterfeit");
			return false;
		}

		if (!$allValid) {
			$this->saveCoinsDB("partlycounterfeit");
			$this->error = "Some coins are counterfeit";	
			cLogger::error("Some coins are counterfeit");
			return false;
		}

		$path =	$this->saveCoinsDB("powned");
		
		$paths = preg_split("/\//", $path);
		$len = count($paths);
		
		$path = base64_encode($paths[$len - 2] . "/" . $paths[$len - 1]);

		return $path;
	}

	private function getMyANs($ans, $idx) {
		$myans = [];

		foreach ($ans as $itemans) {
			$myans[] = "ans[]=" . $itemans[$idx];
		}

		return $myans;
	}
	private function getMyPANs($pans, $idx) {
		$mypans = [];

		foreach ($pans as $itempans) {
			$mypans[] = "pans[]=" . $itempans[$idx];
		}

		return $mypans;
	}

	public function generatePans() {
		$pans = [];
		for ($i = 0; $i < self::RAIDA_NUM; $i++) {
			$v = sprintf('%04X%04X%04X%04X%04X%04X%04X%04X', 
				mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), 
				mt_rand(16384, 20479), mt_rand(32768, 49151), 
				mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));

			$pans[] = strtolower($v);
		}

		return $pans;
	}

	public function getDenomination($num) {
		if ($num > 0 && $num < 2097153)
			$den = 1;
		elseif ($num < 4194305)
			$den = 5;
		elseif ($num < 6291457)
			$den = 25;
		elseif ($num < 14680065)
			$den = 100;
		elseif ($num < 16777217)
			$den = 250;
		else
			$den = 0;

		return $den;
	}

	public function getCMD($cmd, $idx, $postFile = "", $args = []) {

		$url = $this->raida[$idx];
		$args = join(" ", $args);

		$cmd = "/usr/bin/php -f " . $this->dir . "/asynctask.php $url $idx $cmd $postFile $args";

		return $cmd;
	}

	public function initRAIDA() {
		$list = file_get_contents($this->initFile);
		if (!$list) {
			$this->error = "Failed to init RAIDA";
			cLogger::error("Failed to get list");
			return false;
		}

		$list = @json_decode($list);
                $jsonLastError = json_last_error();
		if ($jsonLastError !== JSON_ERROR_NONE) {
			$this->error = "Internal error";
                        cLogger::error("RAIDA: Failed to parse init json: " . $jsonLastError);
                        return false;
                }

		if (!isset($list->networks)) {
			$this->error = "Internal error";
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

				$this->raida[$idx] = $url;
			}
		}
	
		$this->okEcho = 0;	
		$te = new TaskExecutor([$this,"cb"], self::RAIDA_NUM);
		foreach ($this->raida as $idx => $raida) {
			$cmd = $this->getCMD('echo', $idx);

			$te->executeAsync($cmd, $idx);
		}
		
		$te->waitForAllTerminal();

		if ($this->okEcho < self::RAIDA_NUM - 3) {
			$this->error = "RAIDA is not ready";
                        cLogger::error("RAIDA is not ready:{$this->okEcho} out of " . self::RAIDA_NUM);
                        return false;
		}

		return true;
	}
	public function mdcb($result, $error, $private) {
		echo "Multi callback $private\n";

		$rv = @json_decode($result);
                $jsonLastError = json_last_error();
                if ($jsonLastError != JSON_ERROR_NONE) {
                        cLogger::debug("Request failed: $jsonLastError");
                        return null;
                }

		if (!is_array($rv)) {
			cLogger::debug("Weird reply from raida $private");
			return null;
		}

		if (count($rv) != count($this->coinsDB)) {
			cLogger::debug("Weird count of coins from raida $private");
			return null;
		}

		$i = 0;
		foreach ($rv as $coin) {
			if (!isset($coin->status)) {
				cLogger::debug("Weird coin reply freom raida $private: $result");
				return null;
			}

		//	if (in_array($private, [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21]))
		//		$coin->status = RAIDA_COIN_RESULT_VALID;

			if ($coin->status == RAIDA_COIN_RESULT_VALID) 
				$this->coinsDB[$i]['statuses'][$private] = RAIDA_COIN_RESULT_VALID;
			
			$i++;
		}

		$this->progress += 3;
		call_user_func($this->progressCallBack, $this->progress);

		return true;
	}

	public function cb($result, $error, $private) {
		$rv = @json_decode($result);
                $jsonLastError = json_last_error();
                if ($jsonLastError != JSON_ERROR_NONE) {
                        cLogger::debug("Request failed: $jsonLastError");
                        return null;
                }

		if (!$rv || !isset($rv->status)) {
			cLogger::debug("Weird reply freom raida $private");
			return null;
		}

		if ($rv->status == "ready")
			$this->okEcho++;		

		$this->progress++;
		call_user_func($this->progressCallBack, $this->progress);

		return true;
	}

	public function runAgent($idx) {

	}

}

?>
