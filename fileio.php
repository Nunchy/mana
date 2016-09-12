<?php
	/**
	 | fileio.php.
	 |
	 | M. Nealon, 2016.
	 */

	define("LOADFILE_ERR_NULLPARENT", -1);
	define("LOADFILE_ERR_NULLPATH", -2);
	define("LOADFILE_ERR_NOTFOUND", -3);
	 
	/**
	 | The loadFile() function will try to load the specified file
	 | ($filePath).
	 |
	 | Will return either:
	 |
	 |	-1	NULL or empty $parentPath
	 |	-2	NULL or empty $childPath
	 |	-3	Specified file couldn't be found.
	 |
	 | Otherwise, on success the contents of the loaded file are
	 | returned.
	 |
	 | If we call:
	 |
	 |	$fileData = loadFile("SomeDir", NULL, "SomeFile.txt");
	 |
	 | Then the loadFile() function will attempt to load:
	 |
	 |	SomeDir/SomeFile.txt
	 |
	 | If we call:
	 |
	 |	$fileData = loadFile("SomeDir", "SomeSubdir", "SomeFile.txt");
	 |
	 | The loadFile() function will first look for:
	 |
	 |	SomeDir/SomeSubdir/SomeFile.txt
	 |
	 | If the file is found its data is loaded and returned. If the file
	 | is not found, loadFile() will then look for:
	 |
	 |	SomeDir/SomeFile.txt
	 |
	 | Again, if the file is found it is loaded and returned - if the file
	 | is not found in either directory the LOADFILE_ERR_NOTFOUND (-3) is
	 | returned.
	 */
	function loadFile(
		$parentPath,
		$childPath,
		$filePath
	) {
		/* The $parentPath cannot be NULL or empty, however
		 | the $childPath can.
		 |
		 | If the $childPath is NULL or empty, then this function
		 | woll only look for the specified $filePath in the
		 | $parentPath.
		 |
		 | If the $childPath is set, then this function will first
		 | look for $parentPath/$childPath/$filePath. If this file
		 | exists it is loaded and its data returned. If the fire
		 | doesn't exist this function will default to and look for
		 | $parentPath/$fllePath.
		 */
		if ($parentPath ==  NULL || empty($parentPath))
			return LOADFILE_ERR_NULLPARENT;
	
		if ($filePath == NULL || empty($filePath))
			return LOADFILE_ERR_NULLPATH;
			
		if ($childPath != NULL && ! empty($childPath)) {
			/**
			 | Look first in $parentPath/$childPath for the
			 | specified file. If it's found, its contents are
			 | returned.
			 */
			$strPath = $parentPath . "/" . $childPath . "/" . $filePath;
			
			if (is_file($strPath))
				return file_get_contents($strPath);
		}
		
		/**
		 | Look for the file directly within the $parentPath.
		 */
		$strPath = $parentPath . "/" . $filePath;
		
		if (is_file($strPath))
			return file_get_contents($strPath);
		
		/**
		 | File couldn't be loaded.
		 */
		return LOADFILE_ERR_NOTFOUND;
	}

