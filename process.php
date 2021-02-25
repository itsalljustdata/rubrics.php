<?php

date_default_timezone_set('Australia/Perth');

ini_set('memory_limit','2G');
include_once("_classes/XmlElement.php");

$unitName = "";
$shortname = "";
$zipFile = "";
$csvFile = "";


if ($_SERVER["REQUEST_METHOD"] == "POST") {
	$zipFile = $_FILES["zipFile"]["tmp_name"];
	$csvFile = $_FILES["csvFile"]["tmp_name"];
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

if(is_dir('_data/tmp/')) {
	removeDirectory('_data/tmp/');
}

//
//
// Read the GradeCentre CSV file and load into an object
//
//
// echo "<PRE>";
//Print file details
// echo "Upload: " . $_FILES["csvFile"]["name"] . "<br />";
// echo "Type: " . $_FILES["csvFile"]["type"] . "<br />";
// echo "Size: " . ($_FILES["csvFile"]["size"] / 1024) . " Kb<br />";
// echo "Temp file: " . $_FILES["csvFile"]["tmp_name"] . "<br />";

$csvAsArray           = array_map('str_getcsv', file($csvFile));

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
foreach ($csvAsArray[0] as $csvHeaderElement) {
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
} // foreach ($csvAsArray[0] as $csvHeaderElement) {


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
} //foreach ($csvAsArray as $csvRow) {

// print_r ($gcStudents);
// echo "<BR/>";

// echo "</PRE>";  

//
//
// Grab the filenames in the zipfile, and only extract the RESxxxxx.DAT and XML ones
//
//
$filesToExtract = array();
$zip = zip_open($zipFile);
if (is_resource($zip))
  {
  while ($zip_entry = zip_read($zip))
    {
    $zipEntryName = zip_entry_name($zip_entry);
	if ($zipEntryName == 'imsmanifest.xml' || (substr($zipEntryName,-4) == '.dat' && substr($zipEntryName,0,3) == 'res')) {
			array_push ($filesToExtract,$zipEntryName);
	}
  }
zip_close($zip);
}


$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo('_data/tmp/',$filesToExtract);
    $zip->close();
}


$xml = file_get_contents("_data/tmp/imsmanifest.xml");
$data = xml_to_object($xml);
// echo ("<PRE>");
// $xml = file_get_contents("_data/".$unitName."/imsmanifest.xml");
// echo ("**********************************************************************");echo ("<BR/>");
// echo ("xml");                                                                   echo ("<BR/>");
// echo ("**********************************************************************");echo ("<BR/>");
// print_r($xml);


// echo ("**********************************************************************");echo ("<BR/>");
// echo ("data");                                                                  echo ("<BR/>");
// echo ("**********************************************************************");echo ("<BR/>");
// print_r($data);
// echo ("<PRE>");
// echo ("<B>");
// echo ("**********************************************************************");echo ("<BR/>");
// echo ("data->children");                                                        echo ("<BR/>");
// echo ("**********************************************************************");echo ("<BR/>");
// echo ("</B>");
// print_r($data->children);
// echo ("</PRE>");

//Find resource pointers for Rubric data
// echo ("<PRE>");
$learnRubrics = array();			//course/x-bb-rubrics
$courseRubricAssociation = array();	//course/x-bb-crsrubricassocation
$courseRubricEvaluation = array();	//course/x-bb-crsrubriceval
$gradebookLog = array();			//course/x-bb-gradebook
$userList = array();				//course/x-bb-user
$membershipList = array();			//course/x-bb-coursemembership

foreach ($data->children as $element) {
	if($element->name == "resources") {
		// print_r ($element);
		foreach($element->children as $item) {
			if($item->attributes["type"] == "course/x-bb-coursesetting") {
				$unitName  = $item->attributes["bb:title"];
				$shortname = explode(" ", $unitName)[0];
                $unitCode  = explode(".", $shortname)[0];
				// Create the output folder
				$outputFolder = "Results/".$shortname;
				mkdir($outputFolder);
				// "touch" the output folder to update it's date modified if we're re-processing
				touch($outputFolder);
				// delete any existing data in the output folder
				array_map('unlink', glob($outputFolder."/*.csv"));
				array_map('unlink', glob($outputFolder."/*.html"));
	         	}

			if($item->attributes["type"] == "course/x-bb-rubrics") {
				$learnRubrics["file"] = $item->attributes["bb:file"];
				$learnRubrics["identifier"] = $item->attributes["identifier"];
			}
			if($item->attributes["type"] == "course/x-bb-crsrubricassocation") {
				$courseRubricAssociation["file"] = $item->attributes["bb:file"];
				$courseRubricAssociation["identifier"] = $item->attributes["identifier"];
			}
			if($item->attributes["type"] == "course/x-bb-crsrubriceval") {
				$courseRubricEvaluation["file"] = $item->attributes["bb:file"];
				$courseRubricEvaluation["identifier"] = $item->attributes["identifier"];
			}
			if($item->attributes["type"] == "course/x-bb-gradebook") {
				$gradebookLog["file"] = $item->attributes["bb:file"];
				$gradebookLog["identifier"] = $item->attributes["identifier"];
			}
			if($item->attributes["type"] == "course/x-bb-user") {
				$userList["file"] = $item->attributes["bb:file"];
				$userList["identifier"] = $item->attributes["identifier"];
			}
			if($item->attributes["type"] == "membership/x-bb-coursemembership") {
				$membershipList["file"] = $item->attributes["bb:file"];
				$membershipList["identifier"] = $item->attributes["identifier"];
			}
		}

	}
}

// echo ("</PRE>");
// echo ("<PRE>");
// echo ("unitName  : $unitName<BR/>");
// echo ("shortname : $shortname<BR/>");
// echo ("</PRE>");

// echo ("**********************************************************************");echo ("<BR/>");
// echo ("learnRubrics");                                                          echo ("<BR/>");
// echo ("**********************************************************************");echo ("<BR/>");
// print_r($learnRubrics);				//res00198
// echo ("<PRE>");
// echo ("<B>");
// echo ("**********************************************************************");echo ("<BR/>");
// echo ("courseRubricAssociation");                                               echo ("<BR/>");
// echo ("**********************************************************************");echo ("<BR/>");
// echo ("</B>");
// print_r($courseRubricAssociation);	//res00232
// echo ("</PRE>");
// echo ("**********************************************************************");echo ("<BR/>");
// echo ("courseRubricEvaluation");                                                echo ("<BR/>");
// echo ("**********************************************************************");echo ("<BR/>");
// print_r($courseRubricEvaluation);		//res00233
// echo ("**********************************************************************");echo ("<BR/>");
// echo ("gradebookLog");                                                          echo ("<BR/>");
// echo ("**********************************************************************");echo ("<BR/>");
// print_r($gradebookLog);				//res00199

// User List
$userListData = xml_to_object(file_get_contents("_data/tmp/".$userList["file"]));
// echo ("**********************************************************************");echo ("<BR/>");
// echo ("userListData");                                                          echo ("<BR/>");
// echo ("**********************************************************************");echo ("<BR/>");
// print_r($userListData);

$users = array();

foreach ($userListData->children as $userElement) {
	$bbUserId = $userElement->attributes["id"];
	$username = "";
	$studentId = "";
	$firstName = "";
	$lastName = "";
	$email = "";
	$role = "";

	foreach($userElement->children as $userPart) {
		//Attributes
		if($userPart->name == "USERNAME") {
			$username = $userPart->attributes["value"];
		}
		if($userPart->name == "STUDENTID") {
			$studentId = $userPart->attributes["value"];
		}
		if($userPart->name == "NAMES") {
			foreach($userPart->children as $userNamesPart) {
				if($userNamesPart->name == "GIVEN") {
					$firstName = $userNamesPart->attributes["value"];
				}
				if($userNamesPart->name == "FAMILY") {
					$lastName = $userNamesPart->attributes["value"];
				}
			}
		}
		if($userPart->name == "EMAILADDRESS") {
			$email = $userPart->attributes["value"];
		}
		if($userPart->name == "PORTALROLE") {
			foreach($userPart->children as $userRolePart) {
				if($userRolePart->name == "ROLEID") {
					$role = $userRolePart->attributes["value"];
				}
			}
		}
	}

	$users[$bbUserId] = array(
		"username"		=> $username,
		"studentId"		=> $studentId,
		"firstName"		=> $firstName,
		"lastName"		=> $lastName,
		"email"			=> $email,
		"role"			=> $role
	);

}


// Membership List
$membershipListData = xml_to_object(file_get_contents("_data/tmp/".$membershipList["file"]));
$memberships = array();

foreach ($membershipListData->children as $membershipElement) {

	$courseMembershipId = $membershipElement->attributes["id"];
	$bbUserId = "";

	foreach($membershipElement->children as $membershipPart) {
		//Attributes
		if($membershipPart->name == "USERID") {
			$bbUserId = $membershipPart->attributes["value"];
		}
	}

	$memberships[$courseMembershipId] = $bbUserId;

}


// print_r($users);



//Process LearnRubrics file
$rubrics = array();

$learnRubricsdata = xml_to_object(file_get_contents("_data/tmp/".$learnRubrics["file"]));
//print_r($learnRubricsdata);

foreach ($learnRubricsdata->children as $rubricElement) {
	$id = $rubricElement->attributes["id"];
	$title = "";
	$description = "";
	$type = "";
	$maxValue = "";
	$rows = array();
	foreach($rubricElement->children as $rubricPart) {
		//Attributes
		if($rubricPart->name == "Title") {
			$title = $rubricPart->attributes["value"];
		}
		if($rubricPart->name == "Description") {
			$description = $rubricPart->attributes["value"];
		}
		if($rubricPart->name == "Type") {
			$type = $rubricPart->attributes["value"];
		}
		if($rubricPart->name == "MaxValue") {
			$maxValue = $rubricPart->attributes["value"];
		}

		//Rows
		$rubricContent = array();
		if($rubricPart->name == "RubricRows") {
			$row = array();
			foreach($rubricPart->children as $rubricRow) {
				$rowId = $rubricRow->attributes["id"];
				$rowHeader = "";
				$rowPosition = "";
				$rowPercentage = "";
				foreach($rubricRow->children as $rowPart) {
					if($rowPart->name == "Header") {
						$rowHeader = $rowPart->attributes["value"];
					}
					if($rowPart->name == "Position") {
						$rowPosition = $rowPart->attributes["value"];
					}
					if($rowPart->name == "Percentage") {
						$rowPercentage = $rowPart->attributes["value"];
					}

					//Columns
					$rubricColumns = array();
					if($rowPart->name == "RubricColumns") {
						$column = array();

						foreach($rowPart->children as $rowColumn) {
							$colId = "";
							$colHeader = "";
							$colPosition = "";
							$colDescription = "";
							$colPoints = "";
							$colPointsStart = "";
							$colPointsEnd = "";
							$colPercentage = "";
							$colPercentageMax = "";
							$colPercentageMin = "";

							foreach($rowColumn->children as $columnPart) {
								if($columnPart->name == "Header") {
									$colHeader = $columnPart->attributes["value"];
								}
								if($columnPart->name == "Position") {
									$colPosition = $columnPart->attributes["value"];
								}
								if($columnPart->name == "Cell") {
									$colId = $columnPart->attributes["id"];
									foreach($columnPart->children as $colCell) {
										if($colCell->name == "CellDescription") {
											$colDescription = $colCell->attributes["value"];
										}
										if($colCell->name == "NumericPoints") {
											$colPoints = $colCell->attributes["value"];
										}
										if($colCell->name == "NumericStartPointRange") {
											$colPointsStart = $colCell->attributes["value"];
										}
										if($colCell->name == "NumericEndPointRange") {
											$colPointsEnd = $colCell->attributes["value"];
										}
										if($colCell->name == "Percentage") {
											$colPercentage = $colCell->attributes["value"];
										}
										if($colCell->name == "PercentageMax") {
											$colPercentageMax = $colCell->attributes["value"];
										}
										if($colCell->name == "Percentagemin") {
											$colPercentageMin = $colCell->attributes["value"];
										}
									}
								}

							}
							$column[$colId] = array (
								"id"					=> $colId,
								"position"				=> $colPosition,
								"header"				=> $colHeader,
								"description"			=> $colDescription,
								"points"				=> $colPoints,
								"pointsRangeStart"		=> $colPointsStart,
								"pointsRangeEnd"		=> $colPointsEnd,
								"percentage"			=> $colPercentage,
								"percentageMax"			=> $colPercentageMax,
								"percentageMin"			=> $colPercentageMin,
							);

						}
						$rubricColumns[] = $column;
					}

				}
				$row[$rowId] = array(
					"header"		=> $rowHeader,
					"position"		=> $rowPosition,
					"percentage"	=> $rowPercentage,
					"columns"		=> $rubricColumns,
				);
			}
			$rubricContent[] = $row;
		}



	}

	$rubrics[$id] = array(
		"id"			=> $id,
		"title"			=> $title,
		"description"	=> $description,
		"type"			=> $type,
		"maxValue"		=> $maxValue,
		"content"		=> $rubricContent,

	);
}

//print_r($rubrics);






//Process courseRubricEvaluation file
$courseRubricEvaluationdata = xml_to_object(file_get_contents("_data/tmp/".$courseRubricEvaluation["file"]));
//print_r($courseRubricEvaluationdata);
$rubricEvaluations = array();
$gradebookLogEntryIdsToFind = array();



foreach($courseRubricEvaluationdata->children as $rubricEvaluation) {
	//print_r($rubricEvaluation);

	$evalId = "";
	$evalRubricId = "";
	$evalAttemptId = "";
	$evalGroupAttemptId = "";
	$evalGradebookLogId = "";
	$evalSubmissionDate = "";
	$evalComment = "";
	$evalTotalValue = "";
	$evalRubricItems = array();

	$evalId = $rubricEvaluation->attributes["id"];

	foreach($rubricEvaluation->children as $evalItem) {
		


		if($evalItem->name == "RUBRIC_ID") {
			$evalRubricId = $evalItem->attributes["value"];

		}
		if($evalItem->name == "ATTEMPT_ID") {
			$evalAttemptId = $evalItem->attributes["value"];
		}
		if($evalItem->name == "GROUP_ATTEMPT_ID") {
			$evalGroupAttemptId = $evalItem->attributes["value"];
		}
		if($evalItem->name == "GRADEBOOK_LOG_ID") {
			$evalGradebookLogId = $evalItem->attributes["value"];
		}
		
		if($evalItem->name == "RUBRIC_EVAL") {
			
			foreach($evalItem->children as $evalResult) {

				if($evalResult->name == "SUBMISSION_DATE") {
					$evalSubmissionDate = $evalResult->attributes["value"];
				}
				if($evalResult->name == "COMMENTS") {
					foreach($evalResult->children as $commentPart) {
						if($commentPart->name == "COMMENTS_TEXT") {
							$evalComment = $commentPart->content;
						}
					}
				}
				if($evalResult->name == "TOTAL_VALUE") {
					$evalTotalValue = $evalResult->attributes["value"];
				}

				
				if($evalResult->name == "RUBRIC_CELL_EVAL") {
					$cellRowId = "";
					$cellColId = "";
					$cellPercentage = "";
					foreach($evalResult->children as $rubricCellItem) {
						if($rubricCellItem->name == "RUBRIC_ROW_ID") {
							$cellRowId = $rubricCellItem->attributes["value"];
						}
						if($rubricCellItem->name == "RUBRIC_CELL_ID") {
							$cellColId = $rubricCellItem->attributes["value"];
						}
						if($rubricCellItem->name == "SELECTED_PERCENT") {
							$cellPercentage = $rubricCellItem->attributes["value"];
						}
					}
					$rubricItem = array(
						"rowId"			=> $cellRowId,
						"colId"			=> $cellColId,
						"percentage"	=> $cellPercentage,
					);
					$evalRubricItems[] = $rubricItem;
				}

			}
			
		}
		
		
	}

	if($evalGradebookLogId != "") {
		$gradebookLogEntryIdsToFind[$evalGradebookLogId] = $evalGradebookLogId;
		
	$rubricEvaluationItem = array(
			"id"					=> $evalId,
			"rubricId"				=> $evalRubricId,
			"attemptId"				=> $evalAttemptId,
			"groupAttemptId"		=> $evalGroupAttemptId,
			"gradebookLogId"		=> $evalGradebookLogId,
			"submissionDate"		=> $evalSubmissionDate,
			"comment"				=> $evalComment,
			"total"					=> $evalTotalValue,
			"rubricItems"			=> $evalRubricItems,
		);
	$rubricEvaluations[$evalRubricId][$evalId] = $rubricEvaluationItem;
		
	}
	
}

// echo ("**********************************************************************");echo ("<BR/>");
// echo ("rubricEvaluations");                                                     echo ("<BR/>");
// echo ("**********************************************************************");echo ("<BR/>");
// print_r($rubricEvaluations);

// echo ("**********************************************************************");echo ("<BR/>");
// echo ("gradebookLogEntryIdsToFind");                                            echo ("<BR/>");
// echo ("**********************************************************************");echo ("<BR/>");
// print_r($gradebookLogEntryIdsToFind);

//Process GradebookLog

// echo ("**********************************************************************");echo ("<BR/>");
// echo ($gradebookLog["file"]));                                                  echo ("<BR/>");
// echo ("**********************************************************************");echo ("<BR/>");
// print_r(file_get_contents("_data/tmp/".$gradebookLog["file"]));

$gradebookLogdata = xml_to_object(file_get_contents("_data/tmp/".$gradebookLog["file"]));
// echo ("<PRE>");
// echo ("<B>");
// echo ("**********************************************************************");echo ("<BR/>");
// echo ("gradebookLogdata");                                                      echo ("<BR/>");
// echo ("**********************************************************************");echo ("<BR/>");
// echo ("</B>");
// print_r($gradebookLogdata);
// echo ("</PRE>");

$gradebookLogEntries = array();
$gradebookColumns = array();
$locations = array();

foreach($gradebookLogdata->children as $gradebookItem) {

	if($gradebookItem->name == "OUTCOMEDEFINITIONS") {
		foreach($gradebookItem->children as $itemEntry) {
			foreach($itemEntry->children as $itemAttribute) {
				
				if($itemAttribute->name == "TITLE") {
					$title = $itemAttribute->attributes["value"];
				}
				if($itemAttribute->name == "OUTCOMES") {
					
					if($title == "LOCATION") {

						
						foreach($itemAttribute->children as $locationItem) {
							$courseMembershipId = "";
							$value = "";
							foreach($locationItem->children as $locationPart) {
								if($locationPart->name == "COURSEMEMBERSHIPID") {
									$courseMembershipId = $locationPart->attributes["value"];
								}
								if($locationPart->name == "OVERRIDE_GRADE") {
									$value = $locationPart->content;
								}
							}
							$locations[$memberships[$courseMembershipId]] = $value;
						}
					}
				}
			}
		}
	}
	$gradebookColumns["LOCATION"] = $locations;

	if($gradebookItem->name == "GRADE_HISTORY_ENTRIES") {
        
		foreach($gradebookItem->children as $itemEntry) {
			$id = $itemEntry->attributes["id"];
			$userId = "";
			$username = "";
			$firstName = "";
			$lastName = "";
			$graderUsername = "";
			$graderFirstName = "";
			$graderLastName = "";
			$gradeableItemId = "";
			$entryDate = "";
			$course = "";

			if(!in_array($id, $gradebookLogEntryIdsToFind)) {
				continue;
			}
			
			foreach($itemEntry->children as $itemAttribute) {

				if($itemAttribute->name == "USERID") {
					$userId = $itemAttribute->attributes["value"];
				}
				if($itemAttribute->name == "USERNAME") {
					$username = $itemAttribute->attributes["value"];
				}
				if($itemAttribute->name == "FIRSTNAME") {
					$firstName = $itemAttribute->attributes["value"];
				}
				if($itemAttribute->name == "LASTNAME") {
					$lastName = $itemAttribute->attributes["value"];
				}
				if($itemAttribute->name == "DATE_LOGGED") {
					$entryDate = $itemAttribute->attributes["value"];
				}
				if($itemAttribute->name == "MODIFIER_USERNAME") {
					$graderUsername = $itemAttribute->attributes["value"];
				}
				if($itemAttribute->name == "MODIFIER_FIRSTNAME") {
					$graderFirstName = $itemAttribute->attributes["value"];
				}
				if($itemAttribute->name == "MODIFIER_LASTNAME") {
					$graderLastName = $itemAttribute->attributes["value"];
				}
				if($itemAttribute->name == "GRADABLE_ITEM_ID") {
					$gradeableItemId = $itemAttribute->attributes["value"];
				}
				if($itemAttribute->name == "GRADE") {
					$course = $itemAttribute->attributes["value"];
				}
			}

			
			$location = $gradebookColumns['LOCATION'][$userId];
			$studentId = $users[$userId]["studentId"];
			$email = $users[$userId]["email"];

			$gradebookLogEntry = array(
				"id"					=> $id,
				"userId"				=> $userId,
				"username"				=> $username,
				"firstName"				=> $firstName,
				"lastName"				=> $lastName,
				"location"				=> $location,
				"studentId"				=> $studentId,
				"email"					=> $email,
				"graderUsername"		=> $graderUsername,
				"graderFirstName"		=> $graderFirstName,
				"graderLastName"		=> $graderLastName,
				"gradeableItemId"		=> $gradeableItemId,
				"entryDate"				=> $entryDate,
			);

			$gradebookLogEntries[$id] = $gradebookLogEntry;


		}
		
	}
}

// echo ("**********************************************************************"); echo ("<BR/>");
// echo ("gradebookColumns");                                                       echo ("<BR/>");
// echo ("**********************************************************************"); echo ("<BR/>");
// print_r($gradebookColumns); 
// echo ("<PRE>");
// echo ("**********************************************************************"); echo ("<BR/>");
// echo ("gradebookLogEntries");                                                    echo ("<BR/>");
// echo ("**********************************************************************"); echo ("<BR/>");
// print_r($gradebookLogEntries);

// uasort($gradebookLogEntries, function($a, $b) {
    // return $b["userId"].":".$b["entryDate"] <=> $b["userId"].":".$b["entryDate"]; // This one sorts descending
// });

// echo ("**********************************************************************"); echo ("<BR/>");
// echo ("gradebookLogEntries");                                                    echo ("<BR/>");
// echo ("**********************************************************************"); echo ("<BR/>");
// print_r($gradebookLogEntries);

// echo ("</PRE>");

//CSV
$csvContent = array();

//Display
$HTMLBody = "";

// echo ("**********************************************************************"); echo ("<BR/>");
// echo ("rubrics");                                                                echo ("<BR/>");
// echo ("**********************************************************************"); echo ("<BR/>");
// print_r($rubrics);


foreach($rubrics as $rubricInfo) {
	$rubricInfoHTML 	= file_get_contents("_templates/rubricInformation.html");
	$rubricInfoHTML 	= str_replace("###RUBRIC_TITLE###",$rubricInfo["title"],$rubricInfoHTML);
	$rubricInfoHTML 	= str_replace("###RUBRIC_DESC###",$rubricInfo["description"],$rubricInfoHTML);

	$rubricType = $rubricInfo["type"];
	$rubricMaxValue = $rubricInfo["maxValue"];  //Points for this assessment?

	//Get Rows
	$rubricRowMetadata = array();
	$rubricRowsHTML = "";

	
	foreach($rubricInfo["content"] as $rows) {

		$rowCount = 1;
		$rubricColumnMetadata = array();
		foreach($rows as $rowId => $row) {

			


			$rowHTML 	= file_get_contents("_templates/rubricRow.html");
			$rowHTML 	= str_replace("###ROW_COUNT###",$rowCount,$rowHTML);
			$rowHTML 	= str_replace("###ROW_HEADER###",$row["header"],$rowHTML);
			$rowHTML 	= str_replace("###WEIGHT###",$row["percentage"],$rowHTML);



			$rubricRowColumnsHTML = ""; 
			
			foreach($row["columns"] as $columns) {
				foreach($columns as $column) {
					$colHTML 	= file_get_contents("_templates/rubricCell.html");
					$colHTML 	= str_replace("###COL_HEADER###",$column["header"],$colHTML);
					$colHTML 	= str_replace("###COL_DESC###",$column["description"],$colHTML);

					$pointsValue = "";
					switch($rubricType) {
						case "NUMERIC":
						$pointsValue = $column["points"];
						break;
						case "PERCENTAGE_RANGE":
						$pointsValue = $column["percentageMin"]."%-".$column["percentageMax"]."%";
						break;
						case "NUMERIC_RANGE":
						$pointsValue = $column["pointsRangeStart"]." to ".$column["pointsRangeEnd"];
						break;
						default:
						$pointsValue = "";
					}

					$colHTML 	= str_replace("###COL_POINTS###",$pointsValue,$colHTML);

					$rubricRowColumnsHTML .= $colHTML;

					//Column Metadata
					$rubricColumnMetadata[$column["id"]] = array(
						"id"				=> $column["id"],
						"header"			=> $column["header"],
						"description"		=> $column["description"],
						"points"			=> $column["points"],
					);

				}
			}
			$rowHTML 	= str_replace("###COLUMNS###",$rubricRowColumnsHTML,$rowHTML);

			$rubricRowsHTML .= $rowHTML;
			
			//Row Metedata
			$rubricRowMetadata[$rowId] = array(
				"id"			=> $rowId,
				"count"			=> $rowCount,
				"header"		=> $row["header"],
				"weight"		=> $row["percentage"],
				"columns"		=> $rubricColumnMetadata,
			);

			$rowCount++;
		}
	}
	$rubricInfoHTML 	= str_replace("###RUBRIC_ROW###",$rubricRowsHTML,$rubricInfoHTML);
	//End Get Rows



	$headerColsHTML = "";

	// $rubricCSV = $rubricInfo["title"]."\n";
	// $rubricCSV .= "Date,Lastname,Firstname,Username,StudentId,Location,";
	$rubricCSV = "Unit,StudentId,Course,AttemptStatus,gcLocation,Availability,Date,Location,graderName,";

    $rubricCSVdata = array();

	foreach($rubricRowMetadata as $headerRow) {
		$colHTML 	= file_get_contents("_templates/resultColHeaders.html");
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
	$headerRowHTML = file_get_contents("_templates/resultRowHeaders.html");
	$headerRowHTML 	= str_replace("###HEADER_COLS###",$headerColsHTML,$headerRowHTML);

	$rubricInfoHTML 	= str_replace("###HEADER_ROW###",$headerRowHTML,$rubricInfoHTML);

	// $rubricCSV .="Comment";
	$rubricCSV .="\n";

	//Get Results
	$resultRowHTML = "";

	
    // echo ("<PRE>");
    // echo ("<B>shortname : </B> ".$shortname."<BR/>");
    // echo ("<B>unitCode  : </B> ".$unitCode."<BR/>");
    // echo ("</PRE>");

	foreach($rubricEvaluations[$rubricInfo["id"]] as $rubricResult) {
		$rowHTML 	= file_get_contents("_templates/resultRow.html");
		$rowHTML 	= str_replace("###COMMENT###",$rubricResult["comment"],$rowHTML);

		//print_r($rubricResult);
		//Get the student information
		
//			$gradebookLogEntry = array(
//				"id"					=> $id,
//				"userId"				=> $userId,
//				"username"				=> $username,
//				"firstName"				=> $firstName,
//				"lastName"				=> $lastName,
//				"location"				=> $location,
//				"studentId"				=> $studentId,
//				"email"					=> $email,
//				"graderUsername"		=> $graderUsername,
//				"graderFirstName"		=> $graderFirstName,
//				"graderLastName"		=> $graderLastName,
//				"gradeableItemId"		=> $gradeableItemId,
//				"entryDate"				=> $entryDate,
//			);
		
		
		$_index          = $rubricResult["gradebookLogId"];
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

//		$gcStudent         = array ("LastName"      => $csvRow[$csvColPositions["LastName"]]     
//		                           ,"FirstName"     => $csvRow[$csvColPositions["FirstName"]]    
//		                           ,"StudentID"     => $csvRow[$csvColPositions["StudentID"]]    
//		                           ,"Username"      => $csvRow[$csvColPositions["Username"]]     
//		                           ,"Course"        => $csvRow[$csvColPositions["Course"]]       
//		                           ,"AttemptStatus" => $csvRow[$csvColPositions["AttemptStatus"]]
//		                           ,"Location"      => $csvRow[$csvColPositions["Location"]]     
//		                           ,"Last Access"   => $csvRow[$csvColPositions["Last Access"]]     
//		                           ,"Availability"  => $csvRow[$csvColPositions["Availability"]]     
//		                           );
		
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

		//print_r($studentText."\n");
		//print_r($rubricResult["rubricItems"]);

		//Get the result rows in the same order as the rubric table
		$resultRowColumnsHTML = ""; 
		foreach($rubricRowMetadata as $rowMetadata) {
			$thisRowId = $rowMetadata["id"];
			foreach($rubricResult["rubricItems"] as $resultItem) {

				if($resultItem["rowId"] == $thisRowId) {
					//Process this row's (criteris's) selection for this student
					$resultHeader = $rowMetadata["columns"][$resultItem["colId"]]["header"];
					// $rubricCSVrow .= $resultHeader.",";  //The text result (i.e. the cell header)
					//$resultPoints = $rowMetadata["columns"][$resultItem["colId"]]["points"];
					$resultPoints = "";

					switch($rubricType) {
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

					$colHTML  	= file_get_contents("_templates/resultCell.html");
					$colHTML 	= str_replace("###COL_HEADER###",$resultHeader,$colHTML);
					$colHTML 	= str_replace("###COL_POINTS###",$resultPoints,$colHTML);

					$resultRowColumnsHTML .= $colHTML;
					//$rubricCSVrow .= $resultHeader." ".$resultPoints .",";
				}
			}
		}

		$rowHTML 	= str_replace("###RESULT_COLS###",$resultRowColumnsHTML,$rowHTML);

		// $rubricCSVrow .= "\"".$rubricResult["comment"]."\"";
		$rubricCSVrow .= "\n";
        // put each row of CSV data into an array
        array_push ($rubricCSVdata,$rubricCSVrow);

		$resultRowHTML .= $rowHTML;
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

	$rubricInfoHTML 	= str_replace("###RESULT_ROW###",$resultRowHTML,$rubricInfoHTML);
	//End Get Results

	$HTMLBody .= $rubricInfoHTML;
}



$rubricItemHTML = "";
$rubricItemHTMLSRC  = file_get_contents("_templates/rubricFileItem.html");


$studentCSVdata = array();
$studentCSVrow  = "UnitCode,StudentID,Course,AttemptStatus,Location,Availability"."\n";
array_push ($studentCSVdata,$studentCSVrow);
$dataRows = 0;
foreach ($gcStudents as $gcStudent) {
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

$thisrubricItemHTML = str_replace("###RUBRIC###",$studentFileName,$rubricItemHTMLSRC);
$thisrubricItemHTML = str_replace("###RUBRICDATAROWS###",$dataRows,$thisrubricItemHTML);
$rubricItemHTML    .= $thisrubricItemHTML;

foreach($csvContent as $count => $content) {
	$dataRows = count(explode ("\n",$content))-2; // There's always 2 rows (header plus a blank) in each file
	// echo ("<PRE>");
	// echo ("<B>count</B>    ".$count."<BR/>");
	// echo ("<B>dataRows</B> ".$dataRows."<BR/>");
	// echo ("</PRE>");
	$rubricFileName = $shortname."_rubric_".$count.".csv";
	file_put_contents("Results/".$shortname."/".$rubricFileName, $content);
	array_push ($filesToZip,"Results/".$shortname."/".$rubricFileName);
	$thisrubricItemHTML = str_replace("###RUBRIC###",$rubricFileName,$rubricItemHTMLSRC);
	$thisrubricItemHTML = str_replace("###RUBRICDATAROWS###",$dataRows,$thisrubricItemHTML);
	$rubricItemHTML    .= $thisrubricItemHTML;
}

$finalHTML 	= file_get_contents("_templates/base.html");
$finalHTML 	= str_replace("###UNIT###",$unitName,$finalHTML);
$finalHTML 	= str_replace("###RUBRICITEMS###",$rubricItemHTML,$finalHTML);
$finalHTML 	= str_replace("###BODY###",$HTMLBody,$finalHTML);

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
    // Add a file new.txt file to zip using the text specified
    $zip->addFromString('version.txt', 'text to be added to the version.txt file');
 
    // All files are added, so close the zip file.
    $zip->close();
}


header('Location: '.pathinfo( $_SERVER['PHP_SELF'] )['dirname'].'?sort=modified' );

exit;
?>