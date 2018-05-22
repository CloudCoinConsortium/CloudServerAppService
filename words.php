<?php


namespace CloudService;

class Words {

	static $data;

	static function init() {
		$rv = file_get_contents(WORDS_FILENAME);
		if (!$rv) {
			cLogger::error("Unable to open filename " . WORDS_FILENAME);
			return false;
		}

		self::$data = preg_split("/\n/", $rv);
	}

	static function getWord() {
		if (!self::$data) {
			cLogger::error("Words not initialized");
			return false;
		}

		$max = count(self::$data);
		$idx = rand(0, $max);

		return strtolower(self::$data[$idx]);
	}
}


?>
