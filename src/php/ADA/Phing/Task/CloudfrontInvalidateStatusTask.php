<?php

require_once "phing/tasks/ext/Service/Amazon.php";
require_once "phing/types/DataType.php";
require_once "phing/BuildException.php";

require_once "Zend/Config.php";
require_once "Zend/Http/Client.php";

require_once "ADA/Amazon/Authorization.php";
require_once "ADA/Amazon/Cloudfront.php";

class CloudfrontInvalidateStatusTask extends Service_Amazon {
	
	private $_distribution_id;
	
	private $_invalidation_id;
	
	private $_invalidation_status_property;
	
	private $_response_property;
	
	public function setDistributionId($distribution_id) {
		$this->_distribution_id = $distribution_id;
	}
	
	public function setInvalidationId($invalidation_id) {
		$this->_invalidation_id = $invalidation_id;
	}
	
	public function setStatusProperty($invalidation_status_property) {
		$this->_invalidation_status_property = $invalidation_status_property;
	}
	
	public function setResponseProperty($response_property) {
		$this->_response_property = $response_property;
	}
	
	public function main() {
		$client = $this->_getHttpClient();
		$response = $client->request("GET");
		
		$this->_parseResponseAndSetProperty($response);
	}
	
	/**
	 * @return Zend_Config
	 */
	private function _getConfig() {
		$raw_config = array();
		
		if ($this->key !== false) {
			$raw_config['AWSAccessKeyID'] = $this->key;
		}
		
		if (false !== $this->secret) {
			$raw_config['AWSSecretAccessKey'] = $this->secret;
		}
		
		return new Zend_Config($raw_config);
	}
	
	/**
	 * @return Zend_Http_Client 
	 */
	private function _getHttpClient() {
		$endpoint = Amazon_Cloudfront::getApiBaseUrl() . "/distribution/" . $this->_distribution_id . "/invalidation/" . $this->_invalidation_id;
		$current_date = gmdate('D, d M Y H:i:s \G\M\T');
		$auth = new Amazon_Authorization($this->_getConfig());
		
		$client = new Zend_Http_Client($endpoint);
		$client->setHeaders('Date', $current_date);
		$client->setHeaders('Authorization', $auth->getHeader($current_date));
		
		return $client;
	}
	
	private function _parseResponseAndSetProperty(Zend_Http_Response $response) {
		if (!is_null($this->_response_property)) {
			$this->project->setProperty($this->_response_property, $response->getBody());
		}
		
		if (is_null($this->_invalidation_status_property)) {
			return;
		}
		
		$element_to_prop = array();

		if (!is_null($this->_invalidation_status_property)) {
			$element_to_prop['Status'] = $this->_invalidation_status_property;
		}
		
		$xml = new XMLReader();
		$xml->XML($response->getBody());
		
		while (count($element_to_prop) > 0 && $xml->read()) {
			if ($xml->nodeType == XMLReader::ELEMENT) {
				if ($xml->name == "ErrorResponse") {
					$this->_parseErrorAndFail($xml);
				}
				
				if (isset($element_to_prop[$xml->name])) {
					$this->project->setProperty($element_to_prop[$xml->name], $this->_readXmlText($xml));
					unset($element_to_prop[$xml->name]);
				}
			}
		}
	}
	
	private function _parseErrorAndFail(XMLReader $xml) {
		while ($xml->read()) {
			if ($xml->nodeType == XMLReader::ELEMENT && $xml->name == "Message") {
				throw new BuildException("Error while creating Amazon Cloudfront distribution : \"{$this->_readXmlText($xml)}\"");
			}
		}
	}
	
	private function _readXmlText(XMLReader $xml) {
		$name = $xml->name;
		
		$text = "";
		while ($xml->read()) {
			switch ($xml->nodeType) {
				case XMLReader::TEXT:
					$text .= $xml->value;
					break;
				case XMLReader::END_ELEMENT:
					if ($name == $xml->name) {
						return trim($text);
					}
					break;
			}
		}
		
		return trim($text);
	}
	
}