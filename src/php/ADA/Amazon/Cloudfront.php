<?php

class Amazon_Cloudfront {
	
	const ENDPOINT = "cloudfront.amazonaws.com";
	
	const API_VERSION = "2010-11-01";
	
	const USE_SSL = true;
	
	public static function getApiBaseUrl() {
		return "http" . (self::USE_SSL ? 's' : '') . "://" . self::ENDPOINT . "/" . self::API_VERSION;
	}
	
}