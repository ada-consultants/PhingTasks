<?php
class Amazon_Authorization {

	private $_config;

	public function __construct(Zend_Config $config) {
		$this->_config = $config;
	}

	public function getSignature($date) {
		return base64_encode(hash_hmac('sha1', $date, $this->_config->AWSSecretAccessKey, true));
	}

	public function getHeader($date) {
		return 'AWS ' . $this->_config->AWSAccessKeyID . ':' . $this->getSignature($date);
	}

}