<?php

	if (! isset($MANA_PATH_PREFIX))
		$MANA_PATH_PREFIX = "back-end/include/";
	
	require_once($MANA_PATH_PREFIX . "mana/include/fileio.php");
	require_once($MANA_PATH_PREFIX . "mana/include/parser.php");
	
	require_once($MANA_PATH_PREFIX . "mana/classes/DataSet.class.php");
	
	class Mana {
		/**
		 | The dataset is used to provide a means of
		 | declaring, using and exporting variables.
		 |
		 | See the mana/classes/DataSet.class.php file
		 | for more info.
		 */
		public	$dataSetx;
		
		public	$fileNames;
		public	$fileData;
		public	$fileProcess;
		
		public	$parentPath;
		public	$childPath;
		
		public	$compiledOutput;
		
		public	$errMsg;
		
		function __construct($parentPath, $childPath) {
			$this->dataSets = new DataSets;
			
			$this->fileNames = array();
			$this->fileData = array();
			$this->fileProcess = array();
			
			/**
			 | See the mana/include/fileio.php file for
			 | more info on what parentPath and childPath
			 | are used for.
			 */
			$this->parentPath = $parentPath;
			$this->childPath = $childPath;
			
			$this->compiledOutput = "";
			
			$this->errMsg = "";
		}
		
		/**
		 | If there's an error to report, dump it and bail with
		 | true.
		 |
		 | A typical example of use:
		 |
		 |	$manaCompile = new Mana("parentPath/etc", "");
		 |
		 |	$arrFiles = array(
		 |		"File_one.mana",
		 |		"File_two.mana",
		 |		"File_trhee.mana"
		 |	);
		 |	$arrProcess = array(
		 |		true,
		 |		true,
		 |		true
		 |	);
		 |
		 |	$menuCompile->compile();
		 |
		 |	// Handle any errors...
		 |	//
		 |	if ($manaCompile->error())
		 |		// There was an error...
		 |		//
		 |		die();
		 */
		function error() {
			if ($this->errMsg != "") {
				echo $this->errMsg;
				return true;
			}
			
			return false;
		}
		
		/**
		 | Add a file to the list.
		 */
		function addFile($fileName, $fileProcess) {
			if (in_array($fileName, $this->fileNames)) {
				$this->errMsg = "Couldn't add dusplicate file " . $fileName. "!<br />";
				return false;
			}
			
			array_push($this->fileNames, $fileName);
			array_push($this->fileData, "");
			array_push($this->fileProcess, $fileProcess);
			
			return true;
		}
		
		/**
		 * Add an array of files to the list.
		 */
		function addFiles(
			$fileNames,
			$fileProcess
		) {
			for ($fileNo = 0; $fileNo < count($fileNames); $fileNo++) {
				if (! $this->addFile($fileNames[$fileNo], $fileProcess[$fileNo]))
					return false;
			}
			
			return true;
		}
		
		/**
		 | Load a file - the $fileID is the index of one of the fileNames
		 | in the list.
		 |
		 | For example, if we have a fileNames list with 3 entries:
		 |
		 |	fileNames[0] = "File_one.mana";
		 |	fileNames[1] = "File_two.mana";
		 |	fileNames[2] = "File_three.mana";
		 |
		 | The data from File_one.mana is stored in fileData[0], the
		 | data for File_two.mana in fileData[1], etc.
		 */
		function loadFile(
			$fileID
		) {
			if ($fileID < 0 || $fileID >= count($this->fileNames)) {
				$this->errMsg = "File ID " . $fileID . " out of range!<br />";
				return false;
			}
			
			$fileLoadReturn = loadFile(
				$this->parentPath,
				$this->childPath,
				$this->fileNames[$fileID]
			);
			
			/**
			 | See mana/include/fileio.php for a list of possible errors.
			 */
			if ($fileLoadReturn < 0) {
				if ($fileLoadReturn == LOADFILE_ERR_NULLPARENT)
					$this->errMsg = "The parentPath is NULL or empty!<br />";
				else if ($fileLoadReturn == LOADFILE_ERR_NULLPATH)
					$this->errMsg = "NULL or empty file path!<br />";
				else if ($fileLoadReturn == LOADFILE_ERR_NOTFOUND)
					$this->errMsg = "File " . $this->fileNames[$fileID] . " not found!<br />";
				else
					$this->errMsg = "Unknown error: " . $fileLoadReturn;
				
				return false;
			}
			
			/**
			 | File loaded successfully.
			 */
			$this->fileData[$fileID] = $fileLoadReturn;
			
			return true;
		}
		
		/**
		 | Load all of the files in the fileNames list.
		 |
		 | This method basically calls loadFile() for each file
		 | in the fileNames list, thus is returns all of the
		 | same values on error.
		 */
		function loadFiles() {
			for ($fileID = 0; $fileID < count($this->fileNames); $fileID++) {
				if ($this->loadFile($fileID) == false)
					return false;
			}
		}
		
		/**
		 | Compiles all fileData into a single output string.
		 */
		function compile() {
			/**
			 | The compiled output is stored here.
			 */
			$this->compiledOutput = "";
			
			for ($fileID = 0; $fileID < count($this->fileData); $fileID++) {
				if ($this->fileProcess[$fileID]) {
					/**
					 | Process the file. See the mana/include/parse.php
					 | and mana/classes/DataSets.php files for more
					 | info.
					 */
					$this->compiledOutput .= parseFile(
						$this->dataSets,
						$this->fileData[$fileID],
						$this->errMsg
					);
					
					if ($this->errMsg)
						return false;
				}
				else
					/**
					 | No processing on this file.
					 */
					$this->compiledOutput .= $this->fileData[$fileID];
			}
			
			return true;
		}
		
		/**
		 | Exports datasets to JavaScript global namespace and
		 | also to localStorage - see the mana/include/parse.php
		 | and mana/classes/DataSets.php files for more info.
		 */
		function export() {
			$this->dataSets->export();
			
			echo $this->dataSets->exportGlobal;
			echo $this->dataSets->exportLocal;
		}
	}

