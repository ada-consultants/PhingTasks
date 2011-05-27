<?php

require_once "phing/types/DataType.php";
require_once "phing/BuildException.php";

class Origin extends DataType {
	
	private $_type = "custom";
	
	private $_dns_name = null;
	
	private $_http_port = 80;
	
	private $_https_port = 443;
	
	private $_origin_protocol_policy = "match-viewer";
	
	private $_origin_access_identity = null;
	
	public function setType($type) {
		if (!in_array($type, array('custom', 's3'))) {
			throw new BuildException("Invalid type value");
		}
		
		$this->_type = $type;
	}
	
	public function setDnsName($dns_name) {
		$this->_dns_name = $dns_name;
	}
	
	public function setHttpPort($port) {
		$this->_http_port = $port;
	}
	
	public function setHttpsPort($port) {
		$this->_https_port = $port;
	}
	
	public function setOriginProtocolPolicy($policy) {
		if (!in_array($policy, array("match-viewer", "http-only"))) {
			throw new BuildException("Invalid policy value");
		}
		
		$this->_origin_protocol_policy = $policy;
	}
	
	public function setOriginAccessIdentity($identity) {
		$this->_origin_access_identity = $identity;
	}
	
	public function getType() {
		return $this->_type;
	}
	
	public function getDnsName() {
		return $this->_dns_name;
	}
	
	public function getHttpPort() {
		return $this->_http_port;
	}
	
	public function getHttpsPort() {
		return $this->_https_port;
	}
	
	public function getOriginProtocolPolicy() {
		return $this->_origin_protocol_policy;
	}
	
	public function getOriginAccessIdentity() {
		return $this->_origin_access_identity;
	}

	/**
	 * Returns the XML string corresponding to the Cloudfront origin node
	 * 
	 * @return string
	 */
	public function getXmlString() {
		$node_name = ucwords($this->getType()) . "Origin";
		
		$xml  = "<$node_name>";
		$xml .= "<DNSName>{$this->getDnsName()}</DNSName>";
		if (strtolower($this->getType()) == "custom") {
			$xml .= "<HTTPPort>{$this->getHttpPort()}</HTTPPort>";
			$xml .= "<HTTPSPort>{$this->getHttpsPort()}</HTTPSPort>";
			$xml .= "<OriginProtocolPolicy>{$this->getOriginProtocolPolicy()}</OriginProtocolPolicy>";
		} else {
			if ($this->_origin_access_identity) {
				$xml .= "<OriginAccessIdentity>{$this->getOriginAccessIdentity()}</OriginAccessIdentity>";
			}
		}
		$xml .= "</$node_name>";
		
		return $xml;
	}
	
}