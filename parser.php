<?php

/**
 | These sequences open/close a block of code.
 */
define("CODE_BLOCK_OPEN", "{{");
define("CODE_BLOCK_CLOSE", "}}");

define("CODE_EXPR_OPEN", "(");
define("CODE_EXPR_CLOSE", ")");

define("CODE_VARIABLE_ID", "$");
define("CODE_DATASET_ID", "@");
define("CODE_GLOBAL_ID", "!");


/**
 | Boolean function - returns true if the specified character
 | is a quote character.
 */
function isQuoteChar($strChar) {
	if (
		$strChar == '\''	||
		$strChar == "\""	||
		$strChar == "`"
	)
		return true;
		
	return false;
}

/**
 | Parses a block of code into an array of lines and returns
 | the array.
 */
function parseCode($codeData, &$intLine) {
	$linesArray = array();
	
	$strChar = "";
	$intChar = 0;
	
	$strLine = "";
	$strLineNo = "";
	
	$isQuote = false;
	$isComment = false;
	
	while ($intChar < strlen($codeData)) {
		$strChar = substr($codeData, $intChar++, 1);
			
		/**
		 | An unquoted/uncommented ; character ends a line.
		 */
		if (! $isQuote && ! $isComment && $strChar == ";") {
			if (! empty(trim($strLine))) {
				array_push($linesArray, $strLineNo . " " . trim($strLine));
				
				$strLine = "";
				$strLineNo = "";
			}
			
			continue;
		}

		/**
		 | Keep a count of newlines - each line of code is
		 | prepended with a line number token, this is used
		 | for error reporting.
		 */
		if ($strChar == "\n")
			$intLine++;
		
		/**
		 | Handle comments.
		 */
		if (! $isQuote && ! $isComment && $strChar == "/") {
			/**
			 | Look for a trailing * character -- this opens
			 | a comment.
			 */
			$strChar = substr($codeData, $intChar++, 1);
			
			if ($strChar != "*")
				/**
				 * Not a comment.
				 */
				$strChar = substr($codeData, --$intChar, 1);
			else {
				/**
				 | Found a comment opening sequence, this opens
				 | a comment until the first, unquoted closing
				 | sequence is found.
				 */
				$isComment = 1;
				continue;
			}
		}
		
		if (! $isQuote && $isComment && $strChar == "*") {
			/**
			 | Look for a trailing / character -- this closes
			 | an opened comment.
			 */
			$strChar = substr($codeData, $intChar++, 1);
			
			if ($strChar != "/")
				/**
				 | Doesn't terminate the current comment.
				 */
				$strChar = substr($codeData, --$intChar, 1);
			else {
				/**
				 | Found the closing sequence, end the
				 | current comment.
				 */
				$isComment = 0;
				continue;
			}
		}
		
		/**
		 | Handle quotes.
		 */
		if (! $isComment && isQuoteChar($strChar)) {
			/**
			 | Is a quoted string already opened?
			 */
			if ($isQuote) {
				/**
				 | Yes, the $isQuote character tells us which
				 | quote character was used to open the string,
				 | the same character must be found in order to
				 | close the string.
				 |
				 | Non-matching quote characters are preserved
				 | within the current string.
				 */
				if ($strChar == $isQuote)
					$isQuote = false;
			}
			else
				/**
				 | Open a new quoted stirng, the opening quote
				 | is recorded in $inQuote so the terminating,
				 | quote character can be matched.
				 */
				$isQuote = $strChar;
		}
		
		if (! $isComment) {
			if (trim($strLine) == "") {
				$strLineNo = $intLine;
			}
			
			$strLine .= $strChar;
		}
	}
	
	/**
	 | If we reached the end of the input string, there might be a
	 | stray line...
	 */
	if (trim($strLine != ""))
		array_push($linesArray, $strLineNo . " " . trim($strLine));
		
	return $linesArray;
}

function parseFile($dataSets, $fileData, &$strError) {
	$intLine = 1;
	
	$intChar = 0;
	$strChar = "";
	
	$fileOut = "";
	
	$strCode;
	$codeLines;
	
	$lineEval = "";
	
	while ($intChar < strlen($fileData)) {
		// Grab enough characters to check for CODE_BLOCK_START.
		//
		$strChar = substr($fileData, $intChar, strlen(CODE_BLOCK_OPEN));
		
		if ($strChar == CODE_BLOCK_OPEN) {
			$intChar += strlen(CODE_BLOCK_OPEN);
			
			// Read in a new block of code.
			//
			$strCode = "";
			
			while ($intChar < strlen($fileData)) {
				// Again, grab enough chars to check for the
				// CODE_BLOCK_CLOSE sequence.
				//
				$strChar = substr($fileData, $intChar, strlen(CODE_BLOCK_CLOSE));
				
				if ($strChar == CODE_BLOCK_CLOSE) {
					$intChar += strlen(CODE_BLOCK_CLOSE);
					
					// Don't evaluate empty lines.
					//
					if (! empty(trim($strCode))) {
						$codeLines = parseCode($strCode, $intLine);
						
						foreach ($codeLines as $codeLine) {
							$lineEval = evaluateLine($dataSets, $codeLine, $strError);
							
							if (! $lineEval)
								continue;
							
							$fileOut .= $lineEval;
						//	$intChar += strlen(CODE_BLOCK_CLOSE);
						}
					}
					
					break;
				}
				
				$strCode .= substr($fileData, $intChar++, 1);
			}
			
			continue;
		}
		
		$strChar = substr($fileData, $intChar++, 1);
		
		if ($strChar == "\n")
			$intLine++;
		
		$fileOut .= $strChar;
	}
	
	return $fileOut;
}

function evaluateLine($dataSets, $strLine, &$strError) {
	$strOut = "";
	$strToken = "";
	
	$tokensOut = array();
	
	$codeTokens = token_get_all("<?php " . $strLine . " ?>");
	
	foreach ($codeTokens as $codeToken) {
		if (is_array($codeToken))
			$strToken = $codeToken[1];
		else
			$strToken = $codeToken;
		
		if (empty(trim($strToken)))
			continue;
		
		if (trim($strToken) == "<?php" || trim($strToken) == "?>")
			continue;
		
		if (isQuoteChar(trim(substr($strToken, 0, 1)))) {
			if (isQuoteChar(trim(substr($strToken, (strlen($strToken) - 1), 1))))
				$strToken = substr($strToken, 1, (strlen($strToken) - 2));
		}
		
		array_push($tokensOut, $strToken);
	}
	
	/**
	 | $tokensOut[0] specifies the line number, this is used to
	 | produce error reports - i.e error on line $tokensOut[0] ...
	 */
	
	for ($tokenNo = (count($tokensOut) - 1); $tokenNo >= 0; $tokenNo--) {
		if ($tokensOut[$tokenNo] == "=") {
			
			if (($tokenNo + 1) >= count($tokensOut)) {
				$strError = "Line " . $tokensOut[0] . ": Expected <b>r-value</b>";
				return $strOut;
			}
			
			if (($tokenNo - 1) < 1) {
				$strError = "Line " . $tokensOut[0] . ": Expected <b>l-value</b>";
				return $strOut;
			}
			
			$dsName = "";
			$dsRef = false;
			
			$keyVal = $tokensOut[($tokenNo + 1)];
			$keyName = $tokensOut[($tokenNo - 1)];
			
			if (($tokenNo - 2) >= 2) {
				if ($tokensOut[($tokenNo - 2)] == ":") {
					$dsName = $tokensOut[($tokenNo - 3)];
					$dsRef = true;
				}
			}
			
			$dataSets->setVal($dsName, $keyName, $keyVal);
			
			$tokensOut[($tokenNo + 1)] = "";
			$tokensOut[$tokenNo] = "";
			$tokensOut[($tokenNo - 1)] = "";
			
			if ($dsRef) {
				$tokensOut[($tokenNo - 2)] = "";
				$tokensOut[($tokenNo - 3)] = "";
				
				$tokenNo -= 3;
			}
			else
				$tokenNo--;
		}
		
		if ($tokensOut[$tokenNo] == CODE_GLOBAL_ID) {
			if (($tokenNo + 1) >= count($tokensOut)) {
				$strError = "Line " . $tokensOut[0] . ": <b>!</b> expects an identifier";
				return $strOut;
			}
			
			if ($tokensOut[($tokenNo + 1)] == "dataset") {
				$tokensOut[$tokenNo] = $dataSets->currSet;
				$tokensOut[($tokenNo + 1)] = "";
			}
		}
		
		if (substr($tokensOut[$tokenNo], 0, 1) == CODE_VARIABLE_ID) {
			$dsName = "";
			
			$keyName = substr($tokensOut[$tokenNo], 1, (strlen($tokensOut[$tokenNo]) - 1));
			$tokensOut[$tokenNo] = $dataSets->lookupVal($dsName, $keyName);
			
			if ($tokensOut[$tokenNo] < DATASET_OK)
				$tokensOut[$tokenNo] = "NULL";
			
			continue;
		}
		
		if ($tokensOut[$tokenNo] == CODE_DATASET_ID) {
			$dsName = $tokensOut[($tokenNo + 1)];
			
			/**
			 | Two parameters are expected, the : separator followed
			 | by the key reference.
			 */
			if (($tokenNo + 3) < count($tokensOut)) {
				if ($tokensOut[($tokenNo + 2)] != ":") {
					$strError = "Line " . $tokensOut[0] . ": Expected <b>:</b> token.";
					return $strOut;
				}
				
				$keyName = $tokensOut[($tokenNo + 3)];
				$tokensOut[$tokenNo] = $dataSets->lookupVal($dsName, $keyName);
				
				if ($tokensOut[$tokenNo] < DATASET_OK)
					$tokensOut[$tokenNo] = "NULL";
				
				$tokensOut[($tokenNo + 1)] = "";
				$tokensOut[($tokenNo + 2)] = "";
				$tokensOut[($tokenNo + 3)] = "";
			}
			else {
				$strError = "Line " . $tokensOut[0] . ": Expected <b>: &lt;parameter&gt;</b> tokens.";
				return $strOut;
			}
		}
		
		if ($tokensOut[$tokenNo] == CODE_EXPR_OPEN) {
			$intClose = ($tokenNo + 1);
			
			while (true) {
				if ($intClose >= count($tokensOut)) {
					$strError = "Line " . $tokensOut[0] . ": Missing <b>)</b> token";
					return $strOut;
				}
				
				if ($tokensOut[($intClose + 1)] == CODE_EXPR_CLOSE) {
					$tokensOut[($intClose + 1)] = "";
					$intClose--;
					
					break;
				}
				
				$intClose++;
			}
			
			while ($intClose > $tokenNo) {
				if ($intClose == ($tokenNo + 1)) {
					$strError = "Line " . $tokensOut[0] . ": Malformed calculation";
					return $strOut;
				}
				
				if ($tokensOut[$intClose] == "+") {
					$tokensOut[($intClose - 1)] += $tokensOut[($intClose + 1)];
					
					$tokensOut[$intClose] = "";
					$tokensOut[($intClose + 1)] = "";
					
					$intClose -= 2;
				}
				else if ($tokensOut[$intClose] == "-") {
					$tokensOut[($intClose - 1)] -= $tokensOut[($intClose + 1)];
					
					$tokensOut[$intClose] = "";
					$tokensOut[($intClose + 1)] = "";
					
					$intClose -= 2;
				}
				else if ($tokensOut[$intClose] == "*") {
					$tokensOut[($intClose - 1)] *= $tokensOut[($intClose + 1)];
					
					$tokensOut[$intClose] = "";
					$tokensOut[($intClose + 1)] = "";
					
					$intClose -= 2;
				}
				else if ($tokensOut[$intClose] == "/") {
					$tokensOut[($intClose - 1)] /= $tokensOut[($intClose + 1)];
					
					$tokensOut[$intClose] = "";
					$tokensOut[($intClose + 1)] = "";
					
					$intClose -= 2;
				}
				else {
					$strError = "Line " . $tokensOut[0] . ": <b>" . $tokensOut[$intClose] . "</b>: Unknown operator";
					return $strOut;
				}
			}
			
			$tokensOut[$tokenNo] = $tokensOut[($tokenNo + 1)];
			$tokensOut[($tokenNo + 1)] = "";
			
			$tokensOut = removeEmptyTokens($tokensOut);
			$tokenNo = count($tokensOut);
		}
	}
	
	if (count($tokensOut) >= 2) {
		if (trim($tokensOut[1]) == "dataset") {
			if (count($tokensOut) < 4) {
				$strError = "Line " . $tokensOut[0] . ": dataset expects at least <b>2</b> parameters!";
				return $strOut;
			}
	
			if ($tokensOut[2] == "new") {
				/**
				 | Create a new dataset.
				 */
				$setID = $dataSets->lookupSet(trim($tokensOut[3]));
				
				if ($setID < DATASET_OK)
					$dataSets->newSet($tokensOut[3]);
				// else
				//
				//	set exists.
				//
				else {
					$strError = "Line " . $tokensOut[0] . ": Attempt to create existing <b>dataset - " . $tokensOut[3] . "</b>";
					return $strOut;
				}
			}
			else if ($tokensOut[2] == "use") {
				/**
				 | Use/switch current dataset.
				 */
				$setID = $dataSets->lookupSet(trim($tokensOut[3]));
				
				if ($setID >= DATASET_OK)
					$dataSets->currSet = $setID;
				// else
				//
				//	set doesn't exist.
				//
				else {
					$strError = "Line " . $tokensOut[0] . ": Reference <b>" . $tokensOut[3] . "</b> to unknown <b>dataset</b>";
					return $strOut;
				}
			}
			else if ($tokensOut[2] == "export") {
				/**
				 | Export a dataset.
				 */
				$setID = $dataSets->lookupSet(trim($tokensOut[3]));
				
				if ($setID < DATASET_OK) {
					$strError = "Line " . $tokensOut[0] . ": Attempt to <b>export</b> unknown <b>dataset</b>";
					return $strOut;
				}
				
				if (count($tokensOut) > 4) {
					if ($tokensOut[4] == "jsGlobal")
						$dataSets->attr[$setID] = "jsGlobal";
					else if ($tokensOut[4] == "jsLocal")
						$dataSets->attr[$setID] = "jsLocal";
					else if ($tokensOut[4] == "both")
						$dataSets->attr[$setID] = "both";
					else {
						$strError = "Line " . $tokensOut[0] . ": <b>" . $tokensOut[4] . "</b> is an unknown option to <b>export</b>";
						return $strOut;
					}
				}
				else
					$dataSets->attr[$setID] = "both";
			}
			else {
				$strError = "Line " . $tokensOut[0] . ": <b>" . $tokensOut[2] . "</b> is an unknown parameter to <b>dataset</b>";
				return $strOut;
			}
		}
		
		if (trim($tokensOut[1]) == "echo") {
			for ($intToken = 2; $intToken < count($tokensOut); $intToken++) {
				$strOut .= $tokensOut[$intToken];
			}
		}
	}
	
	return $strOut;
}

function removeEmptyTokens($tokens) {
	$tokensOut = array();
	
	foreach ($tokens as $token) {
		if (empty(trim($token)))
			continue;
		
		array_push($tokensOut, $token);
	}
	
	return $tokensOut;
}
