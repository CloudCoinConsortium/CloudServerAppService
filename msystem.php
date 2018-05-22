<?php


namespace CloudService;

require "clogger.php";
require "words.php";
require "mboard.php";
require "core.php";

class mSystem {

	static $rootDir;
	static $db;

	static function init($rootDir) {

		date_default_timezone_set('UTC');

		chdir($rootDir);
		self::setRootDir($rootDir);


		//FIXME: make it work with WorkerMan's sighandlers
		self::installSignals();

		cLogger::init();
		Words::init();
		mBoard::init();
	}

	static function installSignals() {
		pcntl_signal(SIGTERM, ["CloudService\mSystem", "sigHandler"]);
		pcntl_signal(SIGHUP, ["CloudService\mSystem", "sigHandler"]);
		pcntl_signal(SIGINT, ["CloudService\mSystem", "sigHandler"]);
		pcntl_signal(SIGUSR1, ["CloudService\mSystem", "sigHandler"]);
	}

	static function sigHandler() {
	//	Words::deinit();
		echo "SIG";
		exit(1);
	}

	static function setRootDir($rootDir) {
		self::$rootDir = $rootDir;
	}

	static function getRootDir() {
		return self::$rootDir;
	}

	static function getSystemDir() {
		return self::getRootDir() . "/" . SYSTEMDIR;
	}

	static function getTmpDir() {
		return self::getRootDir() . "/" . TMPDIR;
	}

}
