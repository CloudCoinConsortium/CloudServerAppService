<?php

namespace CloudService;

require __DIR__ . "/../chttpapi.php";

use CloudService\cLogger;
use CloudService\cHTTPAPI;



class DetectionAgent extends cHTTPAPI {


	function __construct($idx, $url, $raw = false) {
		$this->idx = $idx;
		$this->url = 'https://' . $url . "/service/";
		$this->data = null;
		$this->status = RAIDA_STATUS_NOTREADY;
		$this->raw = $raw;

                parent::__construct($this->url, "application/x-www-form-urlencoded");
	}

	private function buildRequest() {
		if ($this->data)
			return $this->data;

		return null;
        }

	public function setData($data) {
		$this->data = $data;
	}

	public function getStatus() {
		return $this->status;
	}

        public function _doRequest($method, $params = []) {
                $url = $this->url . "$method";

                $nparams = [];
                foreach ($params as $k => $v)
                        $nparams[] = "$k=$v";

		if (count($nparams) > 0)
			$url .= "?";

                $url .= join("&", $nparams);
                $request = $this->buildRequest();
                $rv = $this->doRequestCommonURL($request, $url);
                if (!$rv) {
                        cLogger::debug("Request failed");
                        return null;
                }

		if ($this->raw)
			return $rv;

                $rv = @json_decode($rv);
                $jsonLastError = json_last_error();
                if ($jsonLastError != JSON_ERROR_NONE) {
                        cLogger::debug("Request failed: $jsonLastError");
                        return null;
                }

                return $rv;
        }


	public function fixStatus() {
		$rv = $this->echo();
		if (isset($rv->status) && $rv->status == "ready")
			$this->status = RAIDA_STATUS_READY;
	}

	function __call($method, $params) {
		$params = array_shift($params);

		return $this->_doRequest($method, $params);

	}


}
?>
