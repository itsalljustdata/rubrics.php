<?php

function urlBase(){
  $uri_parts   = explode('?', $_SERVER['REQUEST_URI'], 2);
  $https       = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off');
  $defaultPort = $https ? '443' : '80';
  $thisPort    = $_SERVER['SERVER_PORT'];
  return sprintf("%s://%s%s%s"
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
$pageTitle = "ECU::Blackboard Rubric Processor"
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="<?php echo($pageTitle);?>">
    <meta name="author" content="">
	<title><?php echo($pageTitle);?></title>
    <!-- Bootstrap core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://www.ecu.edu.au/favicon.ico">   

    <!-- Custom styles for this template -->
    <link href="css/form-validation.css" rel="stylesheet">
  </head>

  <body class="bg-light">

    <div class="container">
      <div class="py-5 text-center">
                <p align="right"><a href="help.html"  
    onclick="window.open('help.html', 
                         'newwindow'); 
              return false;"
 >Help</a></p>
        <h2><?php echo($pageTitle);?></h2>
        <p class="lead">Use this form to process a Blackboard Archive zip file to extract the rubric information.</p>


      </div>

      <div class="row">
        

        <div class="col-md-12 order-md-1">
          <h4 class="mb-3">New Archive zip file</h4>

          <form class="needs-validation" action="./process.php" method="POST" enctype="multipart/form-data" novalidate>

            <label for="exampleFormControlFile1">Archive Zip File</label>
            <input type="file" class="form-control-file" id="zipFile" name="zipFile" required>
            <div class="invalid-feedback">
                You must select a file.
            </div>
            <br />
            <label for="exampleFormControlFile2">GradeCentre student CSV file</label>
            <input type="file" class="form-control-file" id="csvFile" name="csvFile" required>
            <div class="invalid-feedback">
                You must select a file.
            </div>
            <br />
            <button class="btn btn-primary btn-lg btn-block" type="submit">Process</button>
          
          </form>
        </div>
      </div>

      <br />
      <hr />
      <div class="col-md-12 order-md-1">
		<?php
			if (count($resulteList) == 0) {
				echo ("<H3>No Previous Results</H3>\n");
				}
			else
				{
				echo ("<H3>Previous Results</H3>\n");
				echo ("<TABLE CLASS=\"table.sortable\">\n");
				echo ("<TR>");
				 if ($theSort === "modTime") {
					echo("<TH><A HREF=".$theBaseURL.">Filename<A></TH>");
					echo("<TH>Date Modified</TH>");
					echo("<TH>Archives</TH>");
					}
				 else
					{
					echo("<TH>Filename</TH>");
					echo("<TH><A HREF=".$sortModifiedURL.">Date Modified<A></TH>");
					echo("<TH>Archives</TH>");
					}
				echo ("</TR>\n");
				

				foreach($resulteList as $item) {
				  echo("<TR VALIGN=\"TOP\"><TD><a href=\"".$item["url"]."\" target=\"_blank\">".$item["filename"]."</a></TD><TD>".$item['modTime']."</TD>");
				  echo("<TD>");
				  $pp = pathinfo($item["url"]);
				  $srchPath = $pp['dirname']."/".$pp['basename']."/*.zip";
				  // echo($srchPath);
				  $matches = glob($srchPath);
				  if (count($matches) == 0) {
				  	echo ("&nbsp;");
				  	}
				  else {
						echo("<TABLE CLASS=\"table.sortable\">");
						arsort ($matches);
						foreach ($matches as $match) {
							echo "<TR><TD><a href=\"".$match."\">".substr(basename($match),(strlen($pp['basename'])+1),-4)."</a></TD></TR>";
						}
						echo("</TABLE>");
					   }
				  echo("</TD>");
				  echo("</TR>\n");
					}
				echo ("</TABLE>\n");
				}
		?>
      </div>

    </div>

      


    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script>window.jQuery || document.write('<script src="scripts/jquery-slim.min.js"><\/script>')</script>
    <script src="scripts/popper.min.js"></script>
    <script src="scripts/bootstrap.min.js"></script>
    <script src="scripts/holder.min.js"></script>
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
