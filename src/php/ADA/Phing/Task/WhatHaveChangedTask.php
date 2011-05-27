<?php

require_once "phing/types/DataType.php";
require_once "phing/BuildException.php";

require_once "WHC/Compare/FileCompareStrategy.php";
require_once "WHC/Compare/Strategy/CallbackStrategy.php";
require_once "WHC/Compare/Strategy/Md5Strategy.php";
require_once "WHC/Compare/DirectoryCompare.php";
require_once "WHC/Iterator/PathNameFilterIterator.php";

class WhatHaveChangedTask extends Service_Amazon {
	
	private $_old_version_path;
	
	private $_current_version_path;
	
	private $_changes_property;
	
	public function setOldVersionPath($path) {
		$this->_old_version_path = $path;
	}
	
	public function setCurrentVersionPath($path) {
		$this->_current_version_path = $path;
	}
	
	public function setChangesProperty($property) {
		$this->_changes_property = $property;
	}
	
	public function main() {
		if (!is_dir($this->_current_version_path)) {
			throw new BuildExpcetion("The current version path is not a directory");
		}
		
		if (!is_dir($this->_old_version_path)) {
			throw new BuildExpcetion("The old version path is not a directory");
		}
		
		$comparer = new WHC_Compare_DirectoryCompare($this->_current_version_path);
		$comparer->addFilter('.svn');
		
		$results = $comparer->compare($this->_old_version_path);
		
		$paths = "";
		foreach ($results as $result) {
			if (in_array($result['status'], array('D', 'M'))) {
				if ($paths != "") {
					$paths .= "\n";
				}

				$paths .= $result['file'];
			}
		}
		
		if (!is_null($this->_changes_property)) {
			$this->project->setProperty($this->_changes_property, $paths);
		}
		
	}
	
	
	
}