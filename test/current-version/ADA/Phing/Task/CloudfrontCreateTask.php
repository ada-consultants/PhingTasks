<?php

require_once "phing/tasks/ext/Service/Amazon.php";
require_once "phing/types/DataType.php";
require_once "phing/BuildException.php";

require_once "Zend/Config.php";
require_once "Zend/Http/Client.php";

require_once "ADA/Amazon/Authorization.php";
require_once "ADA/Amazon/Cloudfront.php";

require_once "ADA/Phing/Type/Origin.php";

class CloudfrontCreateTask extends Service_Amazon {
	
	/**
	 * @var Origin
	 */
	private $_origin = null;
	
	private $_caller_reference = null;
	
	private $_cname = null;
	
	private $_enabled = true;
	
	/**
	 * @var Comment
	 */
	private $_comment = null;
	
	private $_domain_property = null;
	
	private $_distribution_id_property = null;
	
	public function setCname($cname) {
		$this->_cname = $cname;
	}
	
	public function setEnabled($enabled) {
		if (in_array($enabled, array("true", "TRUE", "1", true, 1))) {
			$enabled = true;
		} else if (in_array($enabled, array("false", "FALSE", "0", null, "", 0))) {
			$enabled = false;
		} else {
			throw new BuildException("Enabled value isn't valid");
		}
		
		$this->_enabled = $enabled;
	}
	
	public function setCallerReference($caller_reference) {
		$this->_caller_reference = $caller_reference;
	}
	
	public function setDomainProperty($property_name) {
		$this->_domain_property = $property_name;
	}
	
	public function setDistributionIdProperty($property_name) {
		$this->_distribution_id_property = $property_name;
	}
	
	public function addComment(CloudfrontComment $comment) {
		if (!is_null($this->_comment)) {
			throw new BuildException("Can't add two comment to a CloudfrontCreate task");
		}
		
		$this->_comment = $comment;
	}
	
	public function addOrigin(Origin $origin) {
		if (!is_null($this->_origin)) {
			throw new BuildException("Can't add two origin to a CloudfrontCreate task");
		}
		
		$this->_origin = $origin;
	}
	
	public function main() {
		$client = $this->_getHttpClient();
		$client->setRawData($this->_getXmlString());
		
		$response = $client->request("POST");
		
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
		$endpoint = Amazon_Cloudfront::getApiBaseUrl() . "/distribution";
		$current_date = gmdate('D, d M Y H:i:s \G\M\T');
		$auth = new Amazon_Authorization($this->_getConfig());
		
		$client = new Zend_Http_Client($endpoint);
		$client->setHeaders('Date', $current_date);
		$client->setHeaders('Authorization', $auth->getHeader($current_date));
		
		return $client;
	}
	
	private function _getXmlString() {
		$xml = new XMLWriter();
		$xml->openMemory();
		$xml->startDocument("1.0", "UTF-8");
		
		$xml->startElement("DistributionConfig");
		$xml->writeAttribute("xmlns", "http://cloudfront.amazonaws.com/doc/2010-11-01/");
		
		// START > Origin
		$xml->writeRaw($this->_origin->getXmlString());
		// END > Origin
		
		// START > CallerReference
		$xml->startElement('CallerReference');
		$xml->text($this->_caller_reference);
		$xml->endElement();
		// END > CallerReference
		
		// START > CNAME
		if (!is_null($this->_cname)) {
			$xml->startElement('CNAME');
			$xml->text($this->_cname);
			$xml->endElement();
		}
		// END > CNAME
		
		// START > Comment
		if (!is_null($this->_comment)) {
			$xml->startElement('Comment');
			$xml->text($this->_comment->getValue());
			$xml->endElement();
		}
		// END > Comment
		
		// START > Enabled
		$xml->startElement('Enabled');
		$xml->text(($this->_enabled ? "true" : "false"));
		$xml->endElement();
		// END > Enabled
		
		$xml->endElement();
		
		return $xml->flush();
	}
	
	private function _parseResponseAndSetProperty(Zend_Http_Response $response) {
		if (is_null($this->_distribution_id_property) && is_null($this->_domain_property)) {
			return;
		}
		
		$element_to_prop = array();

		if (!is_null($this->_distribution_id_property)) {
			$element_to_prop['Id'] = $this->_distribution_id_property;
		}
		
		if (!is_null($this->_domain_property)) {
			$element_to_prop['DomainName'] = $this->_domain_property;
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

class CloudfrontComment extends DataType {
	
	private $_value = "";
	
	public function getValue() {
		return $this->_value;
	}
	
	public function addText($text) {
		$this->_value = trim($text);
	}
	
}