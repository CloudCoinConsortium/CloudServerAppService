<?php

namespace CloudService;

class cLogger {

	private static $instance = null;
	private static $id = null;
	private static $str = array(
		"Error", "Warning", "Info", "Debug"
	);
	const ROTATE_MB_AFTER = 50;

	const LOGTYPE_FILE = 0;
	const MAX_RECORD_LEN = 65535;

	const MSGTYPE_ERROR = 0;
	const MSGTYPE_WARNING = 1;
	const MSGTYPE_INFO = 2;
	const MSGTYPE_DEBUG = 3;

	const NEED_BARRIER = true;

	const CURRENT_LOG_LEVEL = 3;

	const CURRENT_LOG_TYPE = self::LOGTYPE_FILE;

	public static function rotate() {
		if (!self::$instance)
			return;

		if ($type != self::LOGTYPE_FILE)
			return;

		if (filesize(LOG_FILENAME) < self::ROTATE_MB_AFTER * 1024 * 1024)
			return;

		$oldfname = "old" . LOG_FILENAME;
		if (file_exists($oldfname))
			unlink($oldfname);

		rename(LOG_FILENAME, $oldfname);
	}

	public static function init($type = self::LOGTYPE_FILE) {

		if (self::$instance)
			return;

		self::$id = rand(1024,65535);

		switch ($type) {
			case self::LOGTYPE_FILE:
				self::$instance = new cLoggerFile(LOG_FILENAME);
				return;
			default:
				return;
		}
	}

	private static function _log($level, $msg) {
		if ($level > self::CURRENT_LOG_LEVEL)
			return;

		if (!self::$instance)
			self::init(self::CURRENT_LOG_TYPE);

		$instance = self::$instance;
		if (!$instance)
			return;

		$id = "ID:" . self::$id;
		$date = date("d/m/Y H:i:s");
		$string = "$date $id [" . self::$str[$level] . "] $msg\n";

		if (strlen($string) > self::MAX_RECORD_LEN) 
			$string = substr($string, 0, self::MAX_RECORD_LEN - 3) . "...\n";
		
		$instance->log($string);

		if (self::NEED_BARRIER && method_exists($instance, "flush"))
			$instance->flush();
	}

	public static function error($msg) {
		return self::_log(self::MSGTYPE_ERROR, $msg);
	}

	public static function warning($msg) {
		return self::_log(self::MSGTYPE_WARNING, $msg);
	}

	public static function info($msg) {
		return self::_log(self::MSGTYPE_INFO, $msg);
	}

	public static function debug($msg) {
		return self::_log(self::MSGTYPE_DEBUG, $msg);
	}

	public static function finish() {
		self::$instance = null;
	}

}


class cLoggerFile {
	private $fd = null;	

	function __construct($filename) {
		$this->filename = $filename;
		$this->fd = fopen($this->filename, "a+");

		if (!$this->fd)
			return null;
	}

	function log($string) {
		if (!$this->fd)
			return;

		fwrite($this->fd, $string);
	}

	function flush() {
		if (!$this->fd)
			return;

		fflush($this->fd);
	}

	function __destruct() {
		fclose($this->fd);
		$this->fd = null;
	}

}

?>
