<?php

namespace CloudService;

class cHTTPAPI {
	var $needAuth;

	function __construct($url, $contentType = "", $timeout = SOCKET_TIMEOUT) {
		$this->url = $url;
		$this->timeout = $timeout;
		$this->contentType = $contentType;
		$this->errno = 0;
		$this->error = "";
	}

	function doRequestCommon($request) {
		return $this->doRequestCommonURL($request, $this->url);
	}

	function doRequestCommonURL($request, $url) {
		$ch = curl_init();

		if (!is_array($request)) {
			$header[] = "Content-Type: " . $this->contentType;
			$header[] = "Content-Length: " . strlen($request);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		}
	
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
	
		if ($request)
			curl_setopt($ch, CURLOPT_POSTFIELDS, $request);

		if ($this->needAuth) {
			$credentials = $this->login . ":" . $this->password;
			curl_setopt($ch, CURLOPT_USERPWD, $credentials);
		}

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
	//	curl_setopt($ch, CURLOPT_VERBOSE, 1);

		cLogger::debug("Connecting to " . $url);
		$response = curl_exec($ch);
		if (curl_errno($ch)) {
			$this->errno = curl_errno($ch);
			cLogger::error("Failed to perform request: " . curl_error($ch));
			return false;
		}

		$info = curl_getinfo($ch);
		if ($info['http_code'] != 200) {
			cLogger::error("Unexpected HTTP return code: " . $info['http_code']);
			cLogger::debug("Response: " . $response);
			curl_close($ch);
			return false;
		}

		curl_close($ch);

		return $response;
	}

	static function fabric($className, $args) {
		$className = "c$className";

		$object = new $className($args);

		return $object;
	}
}
        

