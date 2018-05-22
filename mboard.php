<?php


namespace CloudService;

class mBoard {

	static $id;

	static function init() {
		$mbId = ftok(__FILE__, 't');
		self::$id = shmop_open($mbId, "c", 0644, 128000);
		if (!self::$id) {
			cLogger::error("Failed to init shmem");
			return false;	
		}

	}

	static function deinit() {
		if (self::$id) {
			shmop_close(self::$id);
			shmop_delete(self::$id);
		}
	}

	static function getWord() {
		if (!self::$data)
			return false;

		$max = count(self::$data);
		$idx = rand(0, $max);

		return strtolower(self::$data[$idx]);
	}
}


?>
