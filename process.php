<?php

$debugMode = false;

date_default_timezone_set('Australia/Perth');

ini_set('memory_limit','2G');
ini_set('max_execution_time','300');
ini_set('log_errors',true);
ini_set('display_errors',$debugMode ? 'stdout' : 'stderr');
ini_set('ignore_repeated_errors',true);

$unitName 			= "";
$shortname 			= "";
$zipFile 			= "";
$csvFile 			= "";
$zipNameOriginal	= "archive.zip";
$csvNameOriginal	= "gradeCentre.csv";
$outputFolder 		= "";

function get_file_contents ($filePath)
{
	$theContent = file_get_contents($filePath); // built-in raises a warning only. We want a fail.
	if ($theContent === false)
		{
			throw new Exception ("File not found : \'" . $filePath . "\'");
		}
	return $theContent;
}

function guidv4($data = null) {
    // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);

    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    // Output the 36 character UUID.
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$tempDir = sys_get_temp_dir() . '/' . guidv4 () . '/';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
	$zipFile 			= $_FILES["zipFile"]["tmp_name"];
	$zipNameOriginal 	= $_FILES["zipFile"]["name"];
	$csvFile 			= $_FILES["csvFile"]["tmp_name"];
	$csvNameOriginal 	= $_FILES["csvFile"]["name"];
}

function saveObjAsJSON ($theObj, $outfile)
{
	GLOBAL $debugMode, $outputFolder;
	if ($debugMode)
	{
		$myfile = fopen($outputFolder . '/' . $outfile, "w") or die("Unable to open file!");
		fwrite($myfile, json_encode($theObj,JSON_PRETTY_PRINT));
		fclose($myfile);
	}
}


function removeDirectory($dir_to_erase) {
    $files = new DirectoryIterator($dir_to_erase);
    foreach ($files as $file) {
        // check if not . or ..
        if (!$file->isDot()) {
            $file->isDir() ? removeDirectory($file->getPathname()) : unlink($file->getPathname());
        }
    }
    rmdir($dir_to_erase);
    return;
}

if(is_dir($tempDir)) {
	removeDirectory($tempDir);
}

$csvAsArray		= array_map('str_getcsv', file($csvFile));

$csvColPositions = array ("LastName"      => -1
                         ,"FirstName"     => -1
                         ,"StudentID"     => -1
                         ,"Username"      => -1
                         ,"Course"        => -1
                         ,"AttemptStatus" => -1
                         ,"Location"      => -1
						 ,"Last Access"   => -1
						 ,"Availability"  => -1
						 );
$csvHeaderColIndex = 0;
foreach ($csvAsArray[0] as $csvHeaderElement)
{
	// echo $csvHeaderRowIndex . ". ~" . $csvHeaderElement . "~<br />";
	if     ($csvColPositions["LastName"]      == -1 && strpos($csvHeaderElement,"Last Name")    !== false)                 {$csvColPositions["LastName"]      = $csvHeaderColIndex;}
	elseif ($csvColPositions["FirstName"]     == -1 && strpos($csvHeaderElement,"First Name")   !== false)                 {$csvColPositions["FirstName"]     = $csvHeaderColIndex;}
	elseif ($csvColPositions["StudentID"]     == -1 && strpos($csvHeaderElement,"Student ID")   !== false)                 {$csvColPositions["StudentID"]     = $csvHeaderColIndex;}
	elseif ($csvColPositions["Username"]      == -1 && strpos($csvHeaderElement,"Username")     !== false)                 {$csvColPositions["Username"]      = $csvHeaderColIndex;}
	elseif ($csvColPositions["Last Access"]   == -1 && strpos($csvHeaderElement,"Last Access")  !== false)                 {$csvColPositions["Last Access"]   = $csvHeaderColIndex;}
	elseif ($csvColPositions["Availability"]  == -1 && strpos($csvHeaderElement,"Availability") !== false)                 {$csvColPositions["Availability"]  = $csvHeaderColIndex;}
	elseif ($csvColPositions["Course"]        == -1 && substr($csvHeaderElement,0,6)             == "COURSE")              {$csvColPositions["Course"]        = $csvHeaderColIndex;}
	elseif ($csvColPositions["AttemptStatus"] == -1 && substr($csvHeaderElement,0,19)            == "UNIT_ATTEMPT_STATUS") {$csvColPositions["AttemptStatus"] = $csvHeaderColIndex;}
	elseif ($csvColPositions["Location"]      == -1 && substr($csvHeaderElement,0,8)             == "LOCATION")            {$csvColPositions["Location"]      = $csvHeaderColIndex;}
	
	$allFound = True;
	foreach ($csvColPositions as $posn) {if ($posn == -1) {$allFound = False; break;}}
	if ($allFound) {break;}
	
	$csvHeaderColIndex++;
}


$gcStudent   = array();
$gcStudents  = array();
$csvRowIndex = 0;
foreach ($csvAsArray as $csvRow) {
	if ($csvRowIndex !== 0) { //we're ignoring the header row (we've dealt with it already
		$gcStudent         = array ("LastName"      => $csvRow[$csvColPositions["LastName"]]     
		                           ,"FirstName"     => $csvRow[$csvColPositions["FirstName"]]    
		                           ,"StudentID"     => $csvRow[$csvColPositions["StudentID"]]    
		                           ,"Username"      => $csvRow[$csvColPositions["Username"]]     
		                           ,"Course"        => $csvRow[$csvColPositions["Course"]]       
		                           ,"AttemptStatus" => $csvRow[$csvColPositions["AttemptStatus"]]
		                           ,"Location"      => $csvRow[$csvColPositions["Location"]]     
		                           ,"Last Access"   => $csvRow[$csvColPositions["Last Access"]]     
		                           ,"Availability"  => $csvRow[$csvColPositions["Availability"]]     
		                           );
		$gcStudents[$gcStudent["StudentID"]] = $gcStudent;
	}
	$csvRowIndex++;
} 

//
// Read the Zip file and imsmanifest.xml to work out which files we are actually interested in
//
$zip = new ZipArchive;
$manifestFilename="imsmanifest.xml";
if (($zip->open($zipFile)) === TRUE)
{
	try
	{
		$manifestIX = $zip->locateName($manifestFilename);
		if (!$manifestIX) {
			throw new Exception('\''. $manifestFilename . '\' not found in zip');
		}
		$manifestContent = $zip->getFromIndex($manifestIX);
	}
	finally
	{
    	$zip->close();
	}
}
else 
{
	throw new Exception('failed to open Zipfile, code:' . $res);
}



$filesToExtract = array();

array_push ($filesToExtract,'imsmanifest.xml');


// $zip = new ZipArchive;
// if ($zip->open($zipFile) === TRUE)
// {
// 	try
// 	{
//     	$zip->extractTo($tempDir,$filesToExtract);
// 	}
// 	finally
// 	{
// 	    $zip->close();
// 	}
// }

function showMemUsage()
{
	global $debugMode;
	if ($debugMode)
	{
	echo "Memory Usage : " . memory_get_usage() . "<BR>";
	}
}
//Find resource pointers for Rubric data
echo ('<PRE>');
showMemUsage();
$learnRubrics = array();			//course/x-bb-rubrics
$courseRubricAssociation = array();	//course/x-bb-crsrubricassocation
$courseRubricEvaluation = array();	//course/x-bb-crsrubriceval
$gradebookLog = array();			//course/x-bb-gradebook
$userList = array();				//course/x-bb-user
$membershipList = array();			//course/x-bb-coursemembership

$renameDetails=array();


function getNewFilename($typeString, $filename)
{
	$exploded = explode("-",$typeString);
	return array	("filetype" =>	$typeString
					,"newName"	=>	end($exploded) . ".xml"
					,"oldName"	=>	(string) $filename
					);
}

function tryToConvert ($val) 
{
	if (strlen($val) === 0)
	{
		$val = NULL;
	}
	else
	{
		if (is_numeric($val))
			{
				$numVal = ($val == (int) $val) ? (int) $val : (float) $val;
				$val = $numVal;
			}
	}
	return $val;
}

function getElementValue ($xmlEle, $elementName) {
	//try 
	{
		if ($elementName == "")
			{
			$val = (string) $xmlEle;
			}
		else
			{
			$val = (string) $xmlEle->$elementName;
			}
	}
	return tryToConvert($val);
}


function getAttrValue ($xmlEle, $elementName, $attrName = "value")
{
	try 
		{
			$initalErrLevel = error_reporting();
			error_reporting (0);
			if ($elementName == "")
				{
				$val = (string) $xmlEle->attributes()-> $attrName;
				}
			else
				{
				$val = (string) $xmlEle->$elementName->attributes()-> $attrName;
				}
		}
	catch (Exception $e)
		{
			$val = (string) $e;
		}
	finally
		{
			error_reporting ($initalErrLevel);
		}
	return tryToConvert($val);
}

$data=simplexml_load_string($manifestContent)->resources; 
showMemUsage();
foreach ($data->resource as $item) {

	$bbAttrs  	=	$item->attributes('bb',true);

	$thisType 	= 	getAttrValue ($item,"","type");
	$thisFileId = 	getAttrValue ($item,"","identifier");
	$thisFile	=	$bbAttrs["file"];

	$thisName	=	getNewFilename ($thisType, $thisFile);
	$proceed	=	True;

	switch ($thisType)
	{
		case  "course/x-bb-coursesetting":
			$unitName 		= $bbAttrs["title"];
			$exploded		= explode(" ", $unitName);
			$shortname		= $exploded[0];
			$unitCode		= explode(".", $shortname)[0];
			array_shift($exploded);
			$unitNameText 	= implode(" ",$exploded);
			// Create the output folder
			$outputFolder = "Results/".$shortname;
			if (!is_dir($outputFolder)) {mkdir($outputFolder);}
			
			// "touch" the output folder to update it's date modified if we're re-processing
			touch($outputFolder);
			// delete any existing data in the output folder
			array_map('unlink', glob($outputFolder."/*.csv"));
			array_map('unlink', glob($outputFolder."/*.html"));
			array_map('unlink', glob($outputFolder."/*.json"));
			break;
		case "course/x-bb-rubrics":
			$learnRubrics["file"]					= $thisName["newName"];
			$learnRubrics["identifier"]				= $thisFileId;
			break;
		case "course/x-bb-crsrubricassocation":
			$courseRubricAssociation["file"]		= $thisName["newName"];
			$courseRubricAssociation["identifier"] 	= $thisFileId;
			break;
		case "course/x-bb-crsrubriceval":
			$courseRubricEvaluation["file"] 		= $thisName["newName"];
			$courseRubricEvaluation["identifier"] 	= $thisFileId;
			break;
		case "course/x-bb-gradebook":
			$gradebookLog["file"] 					= $thisName["newName"];
			$gradebookLog["identifier"] 			= $thisFileId;
			break;
		case "course/x-bb-user":
			$userList["file"] 						= $thisName["newName"];
			$userList["identifier"] 				= $thisFileId;
			break;
		case "membership/x-bb-coursemembership":
			$membershipList["file"] 				= $thisName["newName"];
			$membershipList["identifier"] 			= $thisFileId;
			break;
		default: // We're not interested in this file.
			$proceed = False;
	  	}
	if ($proceed)
	{
		array_push ($renameDetails,$thisName);
		array_push ($filesToExtract,$thisName["oldName"]);
	}
}

saveObjAsJSON ($renameDetails,'renamedetails.json');

//
// now that we've found the names of the resource pointers we're interested in, extract those files
//
$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo($tempDir,$filesToExtract);
    $zip->close();
}
//
// Create a "new" zip with the files we've exracted
//
$inputDataZip = tempnam(sys_get_temp_dir() , "source.zip");

$zip = new ZipArchive;
if ($zip->open($inputDataZip, ZipArchive::CREATE) === TRUE)
{
	foreach ($filesToExtract as $fileToZip) {
		$zip->addFile($tempDir . $fileToZip,$fileToZip);
	}
    $zip->close();
}

//
// Rename the files to something nicer
//
for ($i = 0; $i < count($renameDetails); $i++)
{
	rename($tempDir . $renameDetails[$i]["oldName"], $tempDir .  $renameDetails[$i]["newName"]);
}

function loadTheXML($theFile)
{
	GLOBAL $tempDir;
	//echo "tempDir=" . $tempDir  . "..<BR/>";
	$fullPath =  $tempDir.$theFile;
	return simplexml_load_file($fullPath);
} 

// User List
$userListData= loadTheXML($userList["file"]);
// echo ('<PRE>');
// echo ("**********************************************************************");echo ("<BR/>");
// echo ("userListData");                                                          echo ("<BR/>");
// echo ("**********************************************************************");echo ("<BR/>");
// print_r($userListData);

$users = array();

foreach ($userListData->children() as $userElement) {
	$bbUserId = getAttrValue($userElement,"","id");
	$thisUser =  array	("username"		=> getAttrValue($userElement,"USERNAME")
						,"studentId"	=> getAttrValue($userElement,"STUDENTID")
						,"firstName"	=> getAttrValue($userElement->NAMES,"GIVEN")
						,"lastName"		=> getAttrValue($userElement->NAMES,"FAMILY")
						,"email"		=> getAttrValue($userElement,"EMAILADDRESS")
						,"role"			=> getAttrValue($userElement->PORTALROLE,"ROLEID")
						,"bbUserId"     => $bbUserId
						);
	// print_r ($thisUser);
	$users[$bbUserId] = $thisUser;

}
unset($userListData);
showMemUsage();

echo 'Users : ' . (string) count($users) . "<BR/>";
saveObjAsJSON ($users,$userList["file"] . '.json');

// Membership List
$membershipListData = loadTheXML($membershipList["file"]);
showMemUsage();
$memberships = array();

foreach ($membershipListData->children() as $membershipElement) 
	{
	$memberships[getAttrValue($membershipElement,"","id")] = getAttrValue($membershipElement,"USERID");
	}
	showMemUsage();
unset($membershipListData);
showMemUsage();

// print_r ($memberships);
echo 'Memberships : ' . (string) count($memberships) . "<BR/>";

saveObjAsJSON ($memberships,$membershipList["file"] . '.json');

//Process LearnRubrics file
$rubrics = array();

showMemUsage();
$learnRubricsdata = loadTheXML($learnRubrics["file"]);
showMemUsage();

// foreach ($learnRubricsdata->children as $rubricElement) {
$rubricItem = 0;
foreach ($learnRubricsdata->children() as $rubricElement)
{
	$id = getAttrValue($rubricElement,"","id");
	$rubrics[$id]	= array	("id"			=>	$id
							,"rubricSeq"	=>  ++$rubricItem
							,"title"		=> 	getAttrValue($rubricElement,"Title")
							,"description"	=>  getAttrValue($rubricElement,"Description")
							,"type"			=> 	getAttrValue($rubricElement,"Type")
							,"maxValue"		=>	getAttrValue($rubricElement,"MaxValue")
							,"content"		=> 	array()
							);
	$rubricRowNum = 0;
	foreach ($rubricElement->RubricRows->children() as $rubricRow)
		{
			$rowId 	 = getAttrValue($rubricRow,"","id");
			$thisRow = array	("rowId"			=> 	$rowId
								,"rowNum"			=>  ++$rubricRowNum
								,"rowHeader"		=>	getAttrValue($rubricRow,"Header")
								,"rowPosition"		=> 	getAttrValue($rubricRow,"Position")
								,"rowPercentage"	=> 	getAttrValue($rubricRow,"Percentage")
								,"columns"			=>	array()
								);

			$rubricRowColNum = 0;
			foreach ($rubricRow->RubricColumns->children() as $rowColumn)
				{
				$colId 		= getAttrValue($rowColumn,"Cell","id");
				$thisColumn = array	("id"				=> $colId
									,"colNum"			=>  ++$rubricRowColNum
									,"header"			=> getAttrValue($rowColumn,"Header")
									,"position"			=> getAttrValue($rowColumn,"Position")
									,"description"		=> getAttrValue($rowColumn->Cell,"CellDescription")
									,"points"			=> getAttrValue($rowColumn->Cell,"NumericPoints")
									,"pointsRangeStart"	=> getAttrValue($rowColumn->Cell,"NumericStartPointRange")
									,"pointsRangeEnd"	=> getAttrValue($rowColumn->Cell,"NumericEndPointRange")
									,"percentage"		=> getAttrValue($rowColumn->Cell,"Percentage")
									,"percentageMin"	=> getAttrValue($rowColumn->Cell,"Percentagemin")
									,"percentageMax"	=> getAttrValue($rowColumn->Cell,"PercentageMax")
									);
				$thisRow["columns"][$colId] = $thisColumn;
				}

			$rubrics[$id]["content"][$rowId] = $thisRow;
			//array_push($rubrics[$id]["content"],$thisRow);
		}
}

echo 'Rubrics : ' . (string) count($rubrics) . "<BR/>";

showMemUsage();
saveObjAsJSON ($rubrics,$learnRubrics["file"] . '.json');
unset ($learnRubricsdata);
showMemUsage();

//Process courseRubricEvaluation file
$courseRubricEvaluationdata= loadTheXML($courseRubricEvaluation["file"]);
showMemUsage();

//print_r($courseRubricEvaluationdata);
$rubricEvaluations = array();
$gradebookLogEntryIdsToFind = array();

foreach($courseRubricEvaluationdata->children() as $rubricEvaluation)
{

	$evalId 				=	getAttrValue($rubricEvaluation,"","id");
	$evalRubricId 			=	getAttrValue($rubricEvaluation,"RUBRIC_ID");
	$evalGradebookLogId 	=	getAttrValue($rubricEvaluation,"GRADEBOOK_LOG_ID");

	if($evalGradebookLogId != "") 
		{
		$gradebookLogEntryIdsToFind[$evalGradebookLogId] = $evalGradebookLogId;
		}

	$rubricEvaluationItem	= array	("id"					=> $evalId
									,"rubricId"				=> $evalRubricId
									,"attemptId"			=> getAttrValue($rubricEvaluation,"ATTEMPT_ID")
									,"groupAttemptId"		=> getAttrValue($rubricEvaluation,"GROUP_ATTEMPT_ID")
									,"gradebookLogId"		=> $evalGradebookLogId
									,"rubricEvalId"			=> getAttrValue($rubricEvaluation,"RUBRIC_EVAL","id")
									,"submissionDate"		=> NULL
									,"comment"				=> NULL
									,"total"				=> NULL
									,"rubricItems"			=> array()
									);
	// echo $evalId . "..." . getAttrValue($rubricEvaluation,"RUBRIC_EVAL","id") . "...<BR/>";

	if (! $rubricEvaluationItem["rubricEvalId"] == NULL)
	{
		$rubricEvaluationItem["submissionDate"]	=	getAttrValue($rubricEvaluation->RUBRIC_EVAL,"SUBMISSION_DATE");
		$rubricEvaluationItem["comment"]		=	preg_replace("/\s+/", " ",strip_tags(getElementValue($rubricEvaluation->RUBRIC_EVAL->COMMENTS,"COMMENTS_TEXT")));
		$rubricEvaluationItem["total"]			=	getAttrValue($rubricEvaluation->RUBRIC_EVAL,"TOTAL_VALUE");

		foreach($rubricEvaluation->RUBRIC_EVAL->RUBRIC_CELL_EVAL as $rubricCellEval)
		{
			$rowId = getAttrValue($rubricCellEval,"RUBRIC_ROW_ID");
			$colId = getAttrValue($rubricCellEval,"RUBRIC_CELL_ID");
			array_push	($rubricEvaluationItem["rubricItems"]
						,array	("rowId"		=> $rowId
								,"rowNum"		=> $rubrics[$evalRubricId]["content"][$rowId]["rowNum"]
								,"colId"		=> $colId
								,"colNum"		=> $rubrics[$evalRubricId]["content"][$rowId]["columns"][$colId]["colNum"]
								,"percentage"	=> getAttrValue($rubricCellEval,"SELECTED_PERCENT")
								)
						);
		}
	}
		
	$rubricEvaluations[$evalRubricId][$evalId] = $rubricEvaluationItem;

}
showMemUsage();
unset ($rubricEvaluationItem);
unset ($courseRubricEvaluationdata);
showMemUsage();

echo 'rubricEvaluations : ' . (string) count($rubricEvaluations) . "<BR/>";
echo 'gradebookLogEntryIdsToFind : ' . (string) count($gradebookLogEntryIdsToFind) . "<BR/>";
saveObjAsJSON ($rubricEvaluations,$courseRubricEvaluation["file"] . '.json');
saveObjAsJSON ($gradebookLogEntryIdsToFind,'gradebookLogEntryIdsToFind.json');

$gradebookLogdata = loadTheXML($gradebookLog["file"]);

$gradebookLogEntries = array();
$gradebookColumns = array("LOCATION" => array());

foreach($gradebookLogdata->OUTCOMEDEFINITIONS->children() as $outcomeDef)
{
	// echo $outcomeDef->getname() . "<BR/>";
	if 	(getAttrValue($outcomeDef,"TITLE") == "LOCATION"
		and property_exists($outcomeDef, 'OUTCOMES')
		)
	{

		foreach ($outcomeDef->OUTCOMES->children() as $outcome)
		{
			
			$courseMembershipId = getAttrValue($outcome,"COURSEMEMBERSHIPID");
			if (isset($memberships[$courseMembershipId]))
			{
				$locIndex	= $memberships[$courseMembershipId];
				$location   = null;
				foreach ($outcome->ATTEMPTS->children() as $attempt)
					{
						$location	= 	getAttrValue($attempt,"GRADE");
						if (! $location == null)
						{
							break;
						}
					}
				if (! $location == null)
				{
					// echo $location . "<BR/>";
					$gradebookColumns["LOCATION"][$locIndex] = $location;
				}
			}
		}
	}
}
echo 'Locations : ' . (string) count($gradebookColumns["LOCATION"]) . "<BR/>";
saveObjAsJSON ($gradebookColumns["LOCATION"],'locations.json');
showMemUsage();

foreach($gradebookLogdata->GRADE_HISTORY_ENTRIES->children() as $gradehistoryentry)
{
	$id = getAttrValue($gradehistoryentry,"","id");
	if (isset($gradebookLogEntryIdsToFind[$id]))
	{
		$userId = getAttrValue($gradehistoryentry,"USERID");

		$gradebookLogEntries[$id] = array	("id"					=> $id
											,"userId"				=> $userId 
											,"username"				=> getAttrValue($gradehistoryentry,"USERNAME")
											,"firstName"			=> getAttrValue($gradehistoryentry,"FIRSTNAME")
											,"lastName"				=> getAttrValue($gradehistoryentry,"LASTNAME")
											,"location"				=> isset($gradebookColumns['LOCATION'][$userId]) ? $gradebookColumns['LOCATION'][$userId] : null
											,"studentId"			=> isset($users[$userId]) ? $users[$userId]["studentId"] : null
											,"email"				=> isset($users[$userId]) ? $users[$userId]["email"] : null
											,"graderUsername"		=> getAttrValue($gradehistoryentry,"MODIFIER_USERNAME")
											,"graderFirstName"		=> getAttrValue($gradehistoryentry,"MODIFIER_FIRSTNAME")
											,"graderLastName"		=> getAttrValue($gradehistoryentry,"MODIFIER_LASTNAME")
											,"gradeableItemId"		=> getAttrValue($gradehistoryentry,"GRADABLE_ITEM_ID")
											,"entryDate"			=> getAttrValue($gradehistoryentry,"DATE_LOGGED")
											);
	}

}


echo 'gradebookLogEntries : ' . (string) count($gradebookLogEntries) . "<BR/>";
saveObjAsJSON ($gradebookLogEntries,$gradebookLog["file"] . '.json');
showMemUsage();
unset ($gradebookLogdata);
showMemUsage();

//CSV
$csvContent = array();
$csvContentTitle = array();

//Display
$HTMLBody = "";
$dropdownItems = "";

$rubricTitleBase 			= get_file_contents("_templates/rubricTitle.html");
$rubricTitleWithDescBase 	= get_file_contents("_templates/rubricTitleWithDesc.html");
$rubricInfoBase 			= get_file_contents("_templates/rubricInformation.html");
$rubricResultsBase 			= get_file_contents("_templates/rubricResults.html");

foreach($rubrics as $rubricInfo) {

	if ($rubricInfo["description"] == null or strlen($rubricInfo["description"]) == 0)
		{
			$rubricTitleHTML	= $rubricTitleBase;
		}
	else
		{
			$rubricTitleHTML	= $rubricTitleWithDescBase;
			$rubricTitleHTML 	= str_replace("###RUBRIC_DESC###",$rubricInfo["description"],$rubricTitleHTML);
		}

	$rubricTitleHTML    = str_replace("###RUBRIC_TITLE###",$rubricInfo["title"],$rubricTitleHTML);

	$rubricInfoHTML 	= str_replace("###RUBRIC_TITLE###",$rubricTitleHTML,$rubricInfoBase);
	$rubricInfoHTML    	= str_replace("###RUBRICID###",$rubricInfo["id"],$rubricInfoHTML);

	array_push 	($csvContentTitle
				,array 	("id" 		=>	$rubricInfo["id"]
						,"title"	=>	$rubricInfo["title"]
						)
				);

	$rubricType 	= $rubricInfo["type"];
	$rubricMaxValue = $rubricInfo["maxValue"];  //Points for this assessment?

	//Get Rows
	$rubricRowMetadata	= array();
	$rubricRowsHTML 	= "";

	
	foreach($rubricInfo["content"] as $row) {

		$rowId 		=	$row["rowId"];

		$rubricColumnMetadata = array();
		
		$rowHTML 	= get_file_contents("_templates/rubricRow.html");
		$rowHTML 	= str_replace("###ROW_COUNT###",$row["rowNum"],$rowHTML);
		$rowHTML 	= str_replace("###ROW_HEADER###",$row["rowHeader"],$rowHTML);
		$rowHTML 	= str_replace("###WEIGHT###",$row["rowPercentage"],$rowHTML);

		$rubricRowColumnsHTML = ""; 
		// print_r ($row);
		foreach($row["columns"] as $columns) {
				// print_r ($columns);
				$colHTML 	= get_file_contents("_templates/rubricCell.html");
				$colHTML 	= str_replace("###COL_HEADER###",$columns["header"],$colHTML);
				$colHTML 	= str_replace("###COL_DESC###",$columns["description"],$colHTML);

				$pointsValue = "";
				switch($rubricType) {
					case "NUMERIC":
					$pointsValue = $columns["points"];
					break;
					case "PERCENTAGE_RANGE":
					$pointsValue = $columns["percentageMin"]."%-".$columns["percentageMax"]."%";
					break;
					case "NUMERIC_RANGE":
					$pointsValue = $columns["pointsRangeStart"]." to ".$columns["pointsRangeEnd"];
					break;
					default:
					$pointsValue = "";
				}

				$colHTML 	= str_replace("###COL_POINTS###",$pointsValue,$colHTML);

				$rubricRowColumnsHTML .= $colHTML;

				//Column Metadata
				$rubricColumnMetadata[$columns["id"]] = array(
					"id"				=> $columns["id"],
					"header"			=> $columns["header"],
					"description"		=> $columns["description"],
					"points"			=> $columns["points"],
				);
		}
		$rowHTML 	= str_replace("###COLUMNS###",$rubricRowColumnsHTML,$rowHTML);

		$rubricRowsHTML .= $rowHTML;
		
		//Row Metedata
		$rubricRowMetadata[$row["rowId"]] = array	("id"			=> $row["rowId"]
													,"count"		=> $row["rowNum"]
													,"header"		=> $row["rowHeader"]
													,"weight"		=> $row["rowPercentage"]
													,"columns"		=> $rubricColumnMetadata
													);

	}
	$rubricInfoHTML 	= str_replace("###RUBRIC_ROW###",$rubricRowsHTML,$rubricInfoHTML);
	//End Get Rows

	$headerColsHTML = "";

	// $rubricCSV = $rubricInfo["title"]."\n";
	// $rubricCSV .= "Date,Lastname,Firstname,Username,StudentId,Location,";
	$rubricCSV = "Unit,StudentId,Course,AttemptStatus,gcLocation,Availability,Date,Location,graderName,";

    $rubricCSVdata = array();

	foreach($rubricRowMetadata as $headerRow) {
		$colHTML 	= get_file_contents("_templates/resultColHeaders.html");
		$colHTML 	= str_replace("###HEADER_COUNT###",$headerRow["count"],$colHTML);
		$colHTML 	= str_replace("###HEADER_DESC###",$headerRow["header"],$colHTML);
		$headerColsHTML .= $colHTML;
		// $rubricCSV .= "\"".$headerRow["header"]."\",";
		//Add in extra columns depending on points or percentage
		switch($rubricType) {
			case "NUMERIC":
		        $rubricCSV .= "\"".$headerRow["header"]." Points\",";
				// $rubricCSV .= "Points,";
			break;
			case "PERCENTAGE_RANGE":
		        $rubricCSV .= "\"".$headerRow["header"]." Raw %\",";
		        $rubricCSV .= "\"".$headerRow["header"]." Weighted %\",";
				// $rubricCSV .= "Raw Percentage,";
				// $rubricCSV .= "Weighted Percentage,";
			break;
			case "NUMERIC_RANGE":
		        $rubricCSV .= "\"".$headerRow["header"]." Points\",";
				// $rubricCSV .= "Points,";
			break;
		}
	}
	$headerRowHTML 		= get_file_contents("_templates/resultRowHeaders.html");
	$headerRowHTML 		= str_replace("###HEADER_COLS###",$headerColsHTML,$headerRowHTML);


	// $rubricCSV .="Comment";
	$rubricCSV .="\n";

	//Get Results
	$resultRowHTML = "";

	
    // echo ("<PRE>");
    // echo ("<B>shortname : </B> ".$shortname."<BR/>");
    // echo ("<B>unitCode  : </B> ".$unitCode."<BR/>");
    // echo ("</PRE>");

	if (isset($rubricEvaluations[$rubricInfo["id"]]))
	{
		foreach($rubricEvaluations[$rubricInfo["id"]] as $rubricResult) {
			$_index          = $rubricResult["gradebookLogId"];
			// echo ".." . $_index . "..<BR/>";

			if ($_index == null)
			{
				continue;	
			}

			$rowHTML 	= get_file_contents("_templates/resultRow.html");
			$rowHTML 	= str_replace("###COMMENT###",$rubricResult["comment"],$rowHTML);
			$colCount   = 0;
			$expectedCols = 5; ####!~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
			
			$firstName       = $gradebookLogEntries[$_index]["firstName"];
			$lastName        = $gradebookLogEntries[$_index]["lastName"];
			$username        = $gradebookLogEntries[$_index]["username"];
			$studentId       = $gradebookLogEntries[$_index]["studentId"];
			$location        = $gradebookLogEntries[$_index]["location"];
			$entryDate       = $gradebookLogEntries[$_index]["entryDate"];
			$graderUsername  = $gradebookLogEntries[$_index]["graderUsername"];
			$graderFirstName = $gradebookLogEntries[$_index]["graderFirstName"];
			$graderLastName  = $gradebookLogEntries[$_index]["graderLastName"];
			$gradeableItemId = $gradebookLogEntries[$_index]["gradeableItemId"];
			$studentText     = $lastName.", ".$firstName." (".$username.")";
			$graderText      = "\"".$graderLastName.", ".$graderFirstName." (".$graderUsername.")\"";

			$rowHTML 	= str_replace("###STUDENT###",$studentText,$rowHTML);
			$rowHTML 	= str_replace("###DATE###",$entryDate,$rowHTML);

			
			$gcStudent       = $gcStudents[$studentId];

			$rubricCSVrow  = "";
			$rubricCSVrow .= $unitCode.",";
			$rubricCSVrow .= $studentId.",";
			$rubricCSVrow .= $gcStudent["Course"].",";
			$rubricCSVrow .= $gcStudent["AttemptStatus"].",";
			$rubricCSVrow .= $gcStudent["Location"].",";
			$rubricCSVrow .= $gcStudent["Availability"].",";
			$rubricCSVrow .= $entryDate.",";
			$rubricCSVrow .= $location.",";
			$rubricCSVrow .= $graderText.",";


			//Get the result rows in the same order as the rubric table
			$resultRowColumnsHTML = ""; 
			foreach($rubricRowMetadata as $rowMetadata) {
				$thisRowId = $rowMetadata["id"];
				$foundIt = False;
				foreach($rubricResult["rubricItems"] as $resultItem)
				{

					if($resultItem["rowId"] == $thisRowId) {
						$foundIt = True;
						//Process this row's (criteris's) selection for this student
						$resultHeader = $rowMetadata["columns"][$resultItem["colId"]]["header"]; # . "(" . $thisRowId . ")"
						// $rubricCSVrow .= $resultHeader.",";  //The text result (i.e. the cell header)
						//$resultPoints = $rowMetadata["columns"][$resultItem["colId"]]["points"];
						$resultPoints = "";

						switch($rubricType)
						{
							case "NUMERIC":
								$resultPoints = $rowMetadata["columns"][$resultItem["colId"]]["points"];
								$rubricCSVrow .= $rowMetadata["columns"][$resultItem["colId"]]["points"].",";
								break;

							case "PERCENTAGE_RANGE":
								$thisResultPerc = $resultItem["percentage"] * 100;
								$thisWeightedResult = $thisResultPerc / $rowMetadata["weight"] * 100;
								$resultPoints = $thisWeightedResult."% (".$thisResultPerc."%)";
								$rubricCSVrow .= $thisWeightedResult."%,";  
								$rubricCSVrow .= $thisResultPerc."%,";  
								break;

							case "NUMERIC_RANGE":
								$thisResultPerc = $resultItem["percentage"];
								$thisResultPoints = round($rubricMaxValue * $thisResultPerc,2);
								$resultPoints = $thisResultPoints;
								$rubricCSVrow .= $thisResultPoints.",";
								break;

							default:
								$resultPoints = "";
						}
					}
				}
				if (! $foundIt)
				{
					// no score recorded
					$resultHeader = "<font size=-2><i>No result available</i></font>";
					$resultPoints = "";
					switch($rubricType)
					{
						case "NUMERIC":
							$rubricCSVrow .= ",";
							break;
						case "PERCENTAGE_RANGE":
							$rubricCSVrow .= ",,";
							break;
						case "NUMERIC_RANGE":
							$rubricCSVrow .= ",";
							break;
						default:
							break;
					}
				}
				$colHTML  	= get_file_contents("_templates/resultCell.html");
				$colHTML 	= str_replace("###COL_HEADER###",$resultHeader,$colHTML);
				$colHTML 	= str_replace("###COL_POINTS###",$resultPoints,$colHTML);

				$resultRowColumnsHTML .= $colHTML;
			}

			$rowHTML 	= str_replace("###RESULT_COLS###",$resultRowColumnsHTML,$rowHTML);

			// $rubricCSVrow .= "\"".$rubricResult["comment"]."\"";
			$rubricCSVrow .= "\n";
			// put each row of CSV data into an array
			array_push ($rubricCSVdata,$rubricCSVrow);

			$resultRowHTML .= $rowHTML;
		}
	}

    // (reverse) sort the array of CSV data so that we get the newest one first per student
    arsort ($rubricCSVdata);
    $csvRowPrior = "";
    $dataCount = 0;
    foreach($rubricCSVdata as $csvRow) {
//
	    $dataCount++;
        $thisCSVexplode = explode(",",$csvRow);
//
        if ($dataCount == 1) { // the first row always gets written
			$newStudent = True;
			}
        else { // subsequent rows only get written if they are for a different student
			$newStudent = !($thisCSVexplode[0] == $priorCSVexplode[0] && $thisCSVexplode[1] == $priorCSVexplode[1]);
			}
        if ($newStudent) { // if this is a new student, write it out
			$rubricCSV .= $csvRow;
			}
        // stash these values for comparing with the next row
        $csvRowPrior = $csvRow;
        $priorCSVexplode = $thisCSVexplode;
	}

	$csvContent[] = $rubricCSV;

	if ($dataCount > 0)
	{
		$rubricResultsHTML	=	str_replace("###RESULT_ROW###",$resultRowHTML,$rubricResultsBase);
		$rubricResultsHTML 	= 	str_replace("###HEADER_ROW###",$headerRowHTML,$rubricResultsHTML);
		$rubricResultsHTML 	= 	str_replace("###FOOTER_ROW###",$headerRowHTML,$rubricResultsHTML);
	}
	else
	{
		$rubricResultsHTML 	= 	"<DIV class=\"table-warning\">No results available</DIV>";
	}
	$rubricInfoHTML 	= str_replace("###RUBRIC_RESULTS###",$rubricResultsHTML,$rubricInfoHTML);
	
	//End Get Results
	$HTMLBody .= $rubricInfoHTML;
}

$rubricItemHTML = "";
$rubricItemHTMLSRC  = get_file_contents("_templates/rubricFileItem.html");


$studentCSVdata = array();
$studentCSVrow  = "UnitCode,StudentID,Course,AttemptStatus,Location,Availability"."\n";
array_push ($studentCSVdata,$studentCSVrow);
$dataRows = 0;
foreach ($gcStudents as $gcStudent)
{
	$dataRows++;
	$studentCSVrow =     $unitCode;   
	$studentCSVrow .= ",".$gcStudent ["StudentID"];     
	$studentCSVrow .= ",".$gcStudent ["Course"];
	$studentCSVrow .= ",".$gcStudent ["AttemptStatus"];
	$studentCSVrow .= ",".$gcStudent ["Location"];    
	$studentCSVrow .= ",".$gcStudent ["Availability"];
	$studentCSVrow .= "\n";
	// $studentCSVrow .= ",".$gcStudent ["LastName"]      
	// $studentCSVrow .= ",".$gcStudent ["FirstName"]   
	// $studentCSVrow .= ",".$gcStudent ["Username"]        
	// $studentCSVrow .= ",".$gcStudent ["Last Access"]    
	array_push ($studentCSVdata,$studentCSVrow);
}

$filesToZip = array();

$studentFileName = $shortname.".studentDetails.csv";
file_put_contents("Results/".$shortname."/".$studentFileName, $studentCSVdata);
array_push ($filesToZip,"Results/".$shortname."/".$studentFileName);

$thisrubricItemHTML = str_replace("###RUBRICTITLE###","<I>Student Details</I>",$rubricItemHTMLSRC);
$thisrubricItemHTML = str_replace("###RUBRICID###","",$thisrubricItemHTML);
$thisrubricItemHTML = str_replace("###RUBRIC###",$studentFileName,$thisrubricItemHTML);
$thisrubricItemHTML = str_replace("###RUBRICDATAROWS###",$dataRows,$thisrubricItemHTML);
$rubricItemHTML    .= $thisrubricItemHTML;

$dropDownItemCount  = 0;

foreach($csvContent as $count => $content) {
	$dataRows = count(explode ("\n",$content))-2; // There's always 2 rows (header plus a blank) in each file
	// echo ("<PRE>");
	// echo ("<B>count</B>    ".$count."<BR/>");
	// echo ("<B>dataRows</B> ".$dataRows."<BR/>");
	// echo ("</PRE>");
	$rubricFileName = $shortname."_rubric_".$count.".csv";
	file_put_contents("Results/".$shortname."/".$rubricFileName, $content);
	array_push ($filesToZip,"Results/".$shortname."/".$rubricFileName);
	$dropDownItemCount += 1;
	$dropdownItems 	   .= '<a class="dropdown-item" href="#' . $csvContentTitle[$count]["id"] . '">' . $csvContentTitle[$count]["title"] . '</a>'.PHP_EOL;
	$thisrubricItemHTML = str_replace("###RUBRICTITLE###",$csvContentTitle[$count]["title"],$rubricItemHTMLSRC);
	$thisrubricItemHTML = str_replace("###RUBRICID###",$csvContentTitle[$count]["id"],$thisrubricItemHTML);
	$thisrubricItemHTML = str_replace("###RUBRIC###",$rubricFileName,$thisrubricItemHTML);
	$thisrubricItemHTML = str_replace("###RUBRICDATAROWS###",$dataRows,$thisrubricItemHTML);
	if ($dataRows == 0)
	{
		
		$thisrubricItemHTML = str_replace("class=\"\"","class=\"table-warning\"",$thisrubricItemHTML);
		
	}
	$rubricItemHTML    .= $thisrubricItemHTML;
}

$finalHTML 	= get_file_contents("_templates/base.html");
$finalHTML 	= preg_replace('/<!--(.*)-->/Uis', '', $finalHTML);
$finalHTML 	= str_replace("###UNITCODE###",$shortname,$finalHTML);
$finalHTML 	= str_replace("###UNITNAME###",$unitNameText,$finalHTML);
$finalHTML 	= str_replace("###RUBRICITEMS###",$rubricItemHTML,$finalHTML);
$finalHTML 	= str_replace("###BODY###",$HTMLBody,$finalHTML);
$finalHTML 	= str_replace("###DROPDOWNITEMS###",$dropdownItems,$finalHTML);
$finalHTML 	= str_replace("###DROPDOWNITEMCOUNT###",$dropDownItemCount,$finalHTML);

file_put_contents("Results/".$shortname."/index.html", $finalHTML);
array_push ($filesToZip,"Results/".$shortname."/index.html");

$zippedOutputFilename = "Results/".$shortname."/".$shortname.".".date('Y.m.d H.i.s', time()).".zip";

$zip = new ZipArchive;
if ($zip->open($zippedOutputFilename, ZipArchive::CREATE) === TRUE)
{
    // Add files to the zip file
	foreach ($filesToZip as $fileToZip) {
		// echo $fileToZip . "<BR/>";
		$zip->addFile($fileToZip,basename($fileToZip));
	}
	$zip->addFile($inputDataZip,"source\\" . $zipNameOriginal);
	$zip->addFile($csvFile,"source\\" . $csvNameOriginal);
    // Add a file new.txt file to zip using the text specified
    // $zip->addFromString('version.txt', 'text to be added to the version.txt file');
 
    // All files are added, so close the zip file.
    $zip->close();
}

function deleteIfExists($file)
{
	if (is_file($file))
	{
		unlink($file);
	}
}

if (! $debugMode === true)
{
	if (is_dir($tempDir)) {
		removeDirectory($tempDir);
	}
	deleteIfExists ($inputDataZip);
	deleteIfExists ($zipFile);
	deleteIfExists ($csvFile);
	
}

header('Location: '.pathinfo( $_SERVER['PHP_SELF'] )['dirname'].'?sort=modified' );

exit;
?>