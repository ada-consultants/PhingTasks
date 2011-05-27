<?php

require_once "phing/tasks/ext/Service/Amazon.php";
require_once "phing/types/DataType.php";
require_once "phing/BuildException.php";

require_once "Zend/Config.php";
require_once "Zend/Http/Client.php";

require_once "ADA/Amazon/Authorization.php";
require_once "ADA/Amazon/Cloudfront.php";

class CloudfrontInvalidateTask extends Service_Amazon {
	
	private $_distribution_id;
	
	private $_caller_reference;
	
	private $_invalidation_batch_id_property;
	
	private $_path_lists = array();
	
	public function setDistributionId($distribution_id) {
		$this->_distribution_id = $distribution_id;
	}
	
	public function setCallerReference($caller_refenrece) {
		$this->_caller_reference = $caller_refenrece;
	}
	
	public function setInvalidationBatchIdProperty($invalidation_batch_id_property) {
		$this->_invalidation_batch_id_property = $invalidation_batch_id_property;
	}
	
	public function addPathList(CloudfrontPathlist $list) {
		$this->_path_lists[] = $list;
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
		$endpoint = Amazon_Cloudfront::getApiBaseUrl() . "/distribution/" . $this->_distribution_id . "/invalidation";
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
		
		// START > InvalidationBatch
		$xml->startElement("InvalidationBatch");
		$xml->writeAttribute("xmlns", "http://cloudfront.amazonaws.com/doc/" . Amazon_Cloudfront::API_VERSION . "/");
		
		foreach ($this->_path_lists as $pathlist) {
			$xml->writeRaw($pathlist->getXmlString());
		}
		
		// START > CallerReference
		$xml->startElement('CallerReference');
		$xml->text($this->_caller_reference);
		$xml->endElement();
		// END > CallerReference
		
		$xml->endElement();
		// END > InvalidationBatch
		
		return $xml->flush();
	}
	
	private function _parseResponseAndSetProperty(Zend_Http_Response $response) {
		if (is_null($this->_invalidation_batch_id_property)) {
			return;
		}
		
		$element_to_prop = array();

		if (!is_null($this->_invalidation_batch_id_property)) {
			$element_to_prop['Id'] = $this->_invalidation_batch_id_property;
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

class CloudfrontPathlist extends DataType {
	
	private $_property;
	
	private $_text;
	
	public function setProperty($property) {
		$this->_property = $property;
	}
	
	public function addText($text) {
		$this->_text = trim($text);
	}
	
	public function getList() {
		$list = array();
		
		$prop_value = $this->project->getProperty($this->_property);
		if (is_null($prop_value)) {
			$prop_value = "";
		}

		// Merging the text of the property and the text included in the tag
		$all_paths_text = trim(trim($prop_value) . "\n" . $this->_text);
		
		$list = array_map("trim", explode("\n", $all_paths_text));
		
		return $list;
	}
	
	public function getXmlString() {
		$list = $this->getList();
		if (0 === count($list)) {
			return "";
		}
		
		return "<Path>" . implode("</Path><Path>", $list) . "</Path>";
	}
	
}