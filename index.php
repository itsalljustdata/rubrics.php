<?php

function urlBase()
{
  $uri_parts   = explode('?', $_SERVER['REQUEST_URI'], 2);
  $https       = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off');
  $defaultPort = $https ? '443' : '80';
  $thisPort    = $_SERVER['SERVER_PORT'];
  return sprintf  ("%s://%s%s%s"
                  ,($https ? 'https' : 'http')
				          ,$_SERVER['SERVER_NAME']
				          ,($thisPort == $defaultPort ? '' : ':'.$thisPort)
				          ,$uri_parts[0]
                  );
}

$sortParameter   = "sort";
$sortByModified  = "modified";
$theBaseURL      = urlBase();
$sortModifiedURL = $theBaseURL."?".$sortParameter."=".$sortByModified;

date_default_timezone_set("Australia/Perth"); 

$theSort = "filename";
try
	{
	if(isset($_GET[$sortParameter]) and $_GET[$sortParameter] === $sortByModified) $theSort = "modTime";
	}
catch
	(Exception $e){
	$theSort = "filename";
	}

$resulteList = array();

$dir = new DirectoryIterator("Results");
foreach($dir as $fileInfo) {
  if($fileInfo->isDot()) continue;
  if($fileInfo->getFilename() == "css") continue;
  if($fileInfo->getFilename() == "scripts") continue;

  if($fileInfo->isDir()) {
    $resulteList[] = array('filename'  => $fileInfo->getFilename()
	                      ,'url'       => $fileInfo->getPathname()
						  ,'modTime'   => date ('Y-m-d H:i',$fileInfo->getMTime())
                          );
  }
}
if($theSort === "filename")
	{
uasort($resulteList, function($a, $b) {
    return $a["filename"] <=> $b["filename"]; // This one sorts ascending
});
	}
else
	{
uasort($resulteList, function($a, $b) {
    return $b["modTime"] <=> $a["modTime"]; // This one sorts descending
});
	}
$pageTitle = "Blackboard Rubric Processor";


function grabWebArtefact ($externalURL, $newLocation)
{
  $location = $externalURL;
  if (! file_exists ($newLocation))
    {
      try
      {
        file_put_contents($newLocation, fopen($externalURL, 'r'));
      }
      catch (Exception $e)
      {
        $location = "";
      }
    }
  if (file_exists ($newLocation))
      {
        $location = $newLocation;
      }
  return $location;
}
 
$favicon      = grabWebArtefact ("https://www.ecu.edu.au/favicon.ico", "images/favicon.ico");
$help         = grabWebArtefact ("https://icons.iconarchive.com/icons/treetog/junior/64/help-icon.png", "images/help.png");
$zipIcon      = grabWebArtefact ("https://icons.iconarchive.com/icons/hopstarter/3d-cartoon-vol3/32/Zip-icon.png","images/zip.png");
$modifiedIcon = grabWebArtefact ("https://icons.iconarchive.com/icons/oxygen-icons.org/oxygen/32/Apps-preferences-system-time-icon.png","images/modified.png");
$reportIcon   = grabWebArtefact ("https://icons.iconarchive.com/icons/aha-soft/software/32/reports-icon.png","images/report.png");

?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="<?php echo($pageTitle);?>">
    <meta name="author" content="">
	  <title><?php echo($pageTitle);?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" integrity="sha384-B0vP5xmATw1+K9KRQjQERJvTumQW0nPEzvF6L/Z6nronJ3oUOFUFpCjEUQouq2+l" crossorigin="anonymous">
    <link rel="icon" type="image/x-icon" href="<?php echo($favicon);?>">   

    <!-- <link href="css/form-validation.css" rel="stylesheet"> -->

    <style id="compiled-css" type="text/css">
          .table-fixed tbody 
            {
            height: 500px;
            overflow-y: auto;
            width: 100%;
            }

          .table-fixed thead,
          .table-fixed tbody,
          .table-fixed tr,
          .table-fixed td,
          .table-fixed th
            {
            display: block;
            }

          .table-fixed tbody td,
          .table-fixed tbody th,
          .table-fixed thead > tr > th
          {
            float: left;
            position: relative;

            &::after 
              {
              content: '';
              clear: both;
              display: block;
              }
          }
    </style>


  </head>

  <body class="bg-light">

    <div class="container">
      <p align="right"><img alt="Help" src="<?php echo($help);?>" onclick="window.open('help.html','newwindow'); return false;"></p>
      <div class="py-9 text-center">
      <h2 class="display-4"><?php echo($pageTitle);?></h2>
      <p class="lead">Use this form to process a Blackboard Archive zip file to extract the rubric information.</p>
    </div>
    <hr/>
    <div class="row">
      <div class="col-md-12 order-md-1">
        <!-- <h4 class="mb-3">New Archive zip file</h4> -->

        <form class="needs-validation" id="id" action="./process.php" method="POST" enctype="multipart/form-data" novalidate>
        
          <label for="zipFile">Archive Zip File</label>
          <input type="file" class="form-control-file" id="zipFile" name="zipFile" accept="application/zip" required>
          <div class="invalid-feedback">
              You must select a file.
          </div>
          <br />
          <label for="csvFile">GradeCentre student CSV file</label>
          <input type="file" class="form-control-file" id="csvFile" name="csvFile" accept=".csv" required>
          <div class="invalid-feedback">
              You must select a file.
          </div>
          <br />
          <button class="btn btn-primary btn-lg btn-block" type="submit">Process</button>
        </form>
      </div>
    </div>

    <br/>
    <hr/>
    <br/>

		<?php
			if (count($resulteList) == 0) {
				echo ("<H3 class=\"display-4\">No Previous Results</H3>" . PHP_EOL);
				}
			else
				{
        echo ('<div class="container py-5">' . PHP_EOL);
        echo ('<div class="row">' . PHP_EOL);
        echo ('<div class="col-lg-12 mx-auto bg-white rounded shadow">' . PHP_EOL);

        echo ('<div class="table-responsive">' . PHP_EOL);
        echo ('    <table class="table sortable table-fixed table-striped">' . PHP_EOL);
				echo ("       <caption>Previous Results</caption>" . PHP_EOL);
        echo ('    <thead class="thead-light">' . PHP_EOL);
        echo ('<tr>' . PHP_EOL);
        $headTxt = "Report";
        if (! ($reportIcon == null or strlen($reportIcon) == 0))
          {
            $headTxt = "<IMG SRC=\"$reportIcon\" ALT=\"" . $headTxt . "\"/>";            
          }
        echo("<TH scope=\"col\" class=\"col-5\" data-defaultsign=\"AZ\" data-defaultsort=\"asc\"><A class=\"text-dark\" HREF=".$theBaseURL.">" . $headTxt . "<A></TH>" . PHP_EOL);
        $headTxt = "Date Modified";
        if (! ($modifiedIcon == null or strlen($modifiedIcon) == 0))
          {
            $headTxt = "<IMG SRC=\"$modifiedIcon\" ALT=\"" . $headTxt . "\"/>";            
          }
        echo("<TH scope=\"col\" class=\"col-3\"><A class=\"text-dark\" HREF=".$sortModifiedURL.">" . $headTxt . "<A></TH>" . PHP_EOL);
        $headTxt = "Archives";
        if (! ($zipIcon == null or strlen($zipIcon) == 0))
          {
            $headTxt = "<IMG SRC=\"$zipIcon\" ALT=\"" . $headTxt . "\"/>";
          }
        echo("<TH scope=\"col\" class=\"col-3\">" . $headTxt . "</TH>" . PHP_EOL);
				echo ("</TR>" . PHP_EOL);
        echo ('</thead>' . PHP_EOL);
        echo ('<tbody>' . PHP_EOL);
				

				foreach($resulteList as $item) {
				  echo("<TR VALIGN=\"TOP\">" . PHP_EOL);
				  echo("<TD scope=\"col\" class=\"col-5\"><a class=\".text-success\" href=\"".$item["url"]."\" target=\"_blank\">".$item["filename"]."</a></TD>" . PHP_EOL);
				  echo("<TD scope=\"col\" class=\"col-3\">".$item['modTime']."</TD>" . PHP_EOL);
				  echo("<TD scope=\"col\" class=\"col-3\">" . PHP_EOL);
				  $pp = pathinfo($item["url"]);
				  $srchPath = $pp['dirname']."/".$pp['basename']."/*.zip";
				  // echo($srchPath);
				  $matches = glob($srchPath);
				  if (count($matches) == 0) {
				  	echo ("&nbsp;");
				  	}
				  else {
						arsort ($matches);
            $archiveLine = "";
						foreach ($matches as $match) {
              if (strlen($archiveLine) > 0)
              {
                $archiveLine .= "<BR/>" . PHP_EOL;
              }
              $archiveLine .= "<a href=\"".$match."\">".substr(basename($match),(strlen($pp['basename'])+1),-4)."</a>";
						}
            echo $archiveLine . PHP_EOL;
					  }
				  echo("</TD>" . PHP_EOL);
				  echo("</TR>" . PHP_EOL);
					}
          echo ('</tbody>' . PHP_EOL);
          echo ("</TABLE>" . PHP_EOL);
          echo ("</DIV>" . PHP_EOL);
          echo ("</DIV>" . PHP_EOL);
          echo ("</DIV>" . PHP_EOL);
          echo ("</DIV>" . PHP_EOL);
				}
		?>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-Piv4xVNRyMGpqkS2by6br4gNJ7DXjqk09RmUpJ8jgGtD7zP9yug3goQfGII0yAns" crossorigin="anonymous"></script>
    <script>
      // Example starter JavaScript for disabling form submissions if there are invalid fields
      (function() {
        'use strict';

        window.addEventListener('load', function() {
          // Fetch all the forms we want to apply custom Bootstrap validation styles to
          var forms = document.getElementsByClassName('needs-validation');

          // Loop over them and prevent submission
          var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
              if (form.checkValidity() === false) {
                event.preventDefault();
                event.stopPropagation();
              }
              form.classList.add('was-validated');
            }, false);
          });
        }, false);
      })();
    </script>
  </body>
</html>
