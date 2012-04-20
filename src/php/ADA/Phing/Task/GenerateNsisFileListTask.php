<?php

require_once 'phing/Task.php';
require_once "phing/BuildException.php";
require_once 'phing/types/FileSet.php';

class GenerateNsisFileListTask extends Task {

	private $_filesets = array();

	private $_property = null;

	private $_out_path_prefix = "\$INSTDIR";

	/**
	 * Nested creator, adds a set of files (nested <fileset> attribute).
	 * This is for when you don't care what order files get appended.
	 *
	 * @return FileSet
	 */
	public function createFileSet() {
		$num = array_push($this->_filesets, new FileSet());
		return $this->_filesets[$num-1];
	}

	public function setProperty($property) {
		$this->_property = $property;
	}

	public function setOutPathPrefix($out_path_prefix) {
		$this->_out_path_prefix = $out_path_prefix;
	}

	public function main() {
		$lines = array();

		$files_by_dir = array();
		foreach($this->_filesets as $fs) {
			$files_by_dir = array_merge($files_by_dir, $this->_getFilesByDir($fs));
		}

		foreach ($files_by_dir as $path => $files) {
			if ($path == ".") {
				$path = "";
			} else {
				$path = '\\' . $path;
			}

			$lines[] = "SetOutPath " . $this->_out_path_prefix . $path;
			$lines[] = "";
			foreach ($files as $file) {
				$lines[] = "File $file";
			}
			$lines[] = "";
		}
		
		$this->project->setProperty($this->_property, implode("\n", $lines));
	}

	private function _getFilesByDir(AbstractFileSet $fs) {
		$files_by_dir = array();

		try {
			$basedir = $fs->dir;

			$files = $fs->getDirectoryScanner($this->project)->getIncludedFiles();
			foreach ($files as $file) {
				$relative = $this->_getRelativePath($dir, $file);
				$path = dirname($relative);

				// Translate / to \
				$relative = str_replace("/", "\\", $relative);
				$path = str_replace("/", "\\", $path);

				if (!isset($files_by_dir[$path])) {
					$files_by_dir[$path] = array();
				}

				$files_by_dir[$path][] = $relative;
			}
		} catch (BuildException $be) {
			$this->log($be->getMessage(), Project::MSG_WARN);
		}

		return $files_by_dir;
	}

	/**
	 * Stolen from http://stackoverflow.com/a/2638272/45918
	 */
	private function _getRelativePath($from, $to) {
		$from = explode('/', $from);
		$to = explode('/', $to);
		$relPath = $to;

		foreach ($from as $depth => $dir) {
			// find first non-matching dir
			if ($dir === $to[$depth]) {
				// ignore this directory
				array_shift($relPath);
			} else {
				// get number of remaining dirs to $from
				$remaining = count($from) - $depth;
				if ($remaining > 1) {
					// add traversals up to first matching dir
					$padLength = (count($relPath) + $remaining - 1) * -1;
					$relPath = array_pad($relPath, $padLength, '..');
					break;
				}
			}
		}
		return implode('/', $relPath);
	}

}
