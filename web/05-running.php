<?php
/**
 * Copyright (c) 2012 University of Illinois, NCSA.
 * All rights reserved. This program and the accompanying materials
 * are made available under the terms of the 
 * University of Illinois/NCSA Open Source License
 * which accompanies this distribution, and is available at
 * http://opensource.ncsa.illinois.edu/license.html
 */

// Check login
require("common.php");
open_database();
if ($authentication) {
  if (!check_login()) {
    header( "Location: index.php");
    close_database();
    exit;
  }
}

# boolean parameters
$offline=isset($_REQUEST['offline']) ? "&offline=offline" : "";

// workflowid
if (!isset($_REQUEST['workflowid'])) {
  die("Need a workflowid.");
}
$workflowid=$_REQUEST['workflowid'];

// number of log lines
$loglines = isset($_REQUEST['loglines']) ? $_REQUEST['loglines'] : 20;

// get run information
$stmt = $pdo->prepare("SELECT folder, params FROM workflows WHERE workflows.id=?");
if (!$stmt->execute(array($workflowid))) {
  die('Invalid query: ' . error_database());
}
$workflow = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor();

$folder = $workflow['folder'];
$params = eval("return ${workflow['params']};");

// check result
if (file_exists($folder . DIRECTORY_SEPARATOR . "STATUS")) {
  $status=file($folder . DIRECTORY_SEPARATOR . "STATUS");
} else {
  $status=array();
}

// quick checks for error and finished
$finished = false;
$error = false;
$title = "Job Executing";
$message = "Job is currently executing, please wait.";
foreach ($status as $line) {
  $data = explode("\t", $line);
  if ((count($data) >= 4) && ($data[3] == 'ERROR')) {
    $title = "Job Failed";
    $message = "Job has finished with an error in : " . $data[0];
    $message .= "<br/>Press Finished to see the results.";
    // only send email if finished_at is not set.
    if (isset($params['email']) && ($params['email'] != "")) {
      $url = (isset($_SERVER['HTTPS']) ? "https://" : "http://");
      $url .= $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER["SCRIPT_NAME"];
      $url .= "?workflowid=${workflowid}&loglines=${loglines}";
      if ($offline) {
        $url .= "&offline=offline";
      }
      mail($params['email'], "Workflow has failed", "You can find the results on $url");
    }
    $stmt = $pdo->prepare("UPDATE workflows SET finished_at=NOW() WHERE id=? AND finished_at IS NULL");
    if (!$stmt->execute(array($workflowid))) {
      die('Invalid query: ' . error_database());
    }
    $finished = true;
    $error = true;
  }
  if ($data[0] == "ADVANCED" && count($data) < 3) {
    header( "Location: 06-edit.php?workflowid=${workflowid}${offline}");
    close_database();
    exit;
  }
  if ($data[0] == "FINISHED" && count($data) >= 3) {
    $title = "Job Finished";
    $message = "Job has finished successfully.";
    $message .= "<br/>Press Finished to see the results.";
    $finished = true;
  }
}
close_database();

# get read for refresh
if (!$finished) {
  header( "refresh:5" );
}

?>
<!DOCTYPE html>
<html>
<head>
<title><?php echo $title; ?></title>
<link rel="shortcut icon" type="image/x-icon" href="favicon.ico" />`
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" type="text/css" href="sites.css" />
<script type="text/javascript" src="jquery-1.7.2.min.js"></script>
<script type="text/javascript">
  function prevStep() {
    $("#formprev").submit();
  }

  function nextStep() {
    $("#formnext").submit();
  }

  function refresh() {
    var url="<?php echo $_SERVER["SCRIPT_NAME"] . '?workflowid=' . $workflowid; ?>";
    url += "&loglines=" + $("#loglines").val();
    window.location.replace(url);
    return false;
  }
</script>
</head>
<body>
<div id="wrap">
  <div id="stylized">
    <h1><?php echo $title; ?></h1>
    <p><?php echo $message; ?></p>
    
    <form id="formprev" method="POST" action="02-modelsite.php">
<?php if ($offline != "") { ?>
      <input name="offline" type="hidden" value="offline">
<?php } ?>
    </form>
    
    <form id="formnext" method="POST" action="08-finished.php">
<?php if ($offline != "") { ?>
      <input name="offline" type="hidden" value="offline">
<?php } ?>
      <input type="hidden" name="workflowid" value="<?php echo $workflowid; ?>" />
      <input type="hidden" name="loglines" value="<?php echo $loglines; ?>" />
    </form>

    <span id="error" class="small">&nbsp;</span>
<?php if (!$authentication || (get_page_acccess_level() <= $min_run_level)) { ?>
      <input id="prev" type="button" value="Start Over" onclick="prevStep();"/>
<?php } ?>
<?php if ($finished) { ?>
    <input id="next" type="button" value="Finished" onclick="nextStep();" />
<?php } else { ?>
    <input id="next" type="button" value="Results" onclick="nextStep();" />
<?php } ?>
    <div class="spacer"></div>
<?php whoami(); ?>    
  </div>
  <div id="output">
  <h2>Execution Status</h2>
  <table border=1>
    <tr>
      <th>Stage Name</th>
      <th>Start Time</th>
      <th>End Time</th>
      <th>Status</th>
    </tr>
<?php
foreach ($status as $line) {
  $data = explode("\t", $line);
  echo "    <tr>\n";
  if ($data[0] == "BrownDog") {
    echo "      <td><a href=\"http://browndog.ncsa.illinois.edu\">";
    echo "${data[0]} <img src=\"images/browndog-small-transparent.gif\" alt=\"BrownDog\" width=\"16px\"></a></td>\n";
  } else {
    echo "      <td>${data[0]}</td>\n";    
  }
  if (count($data) >= 2) {
    echo "      <td>${data[1]}</td>\n";
  } else {
    echo "      <td></td>\n";    
  }
  if (count($data) >= 3) {
    echo "      <td>${data[2]}</td>\n";
  } else {
    echo "      <td></td>\n";    
  }
  if (count($data) >= 4) {
    echo "      <td>${data[3]}</td>\n";
  } else {
    echo "      <td>RUNNING</td>\n";        
  }
  echo "    <t/r>\n";
}
?>
  </table>
<?php if ($error) { ?>
  <h2>ERROR</h2>
  There was an error in the execution of the workflow. Please see the log below, or see the
  full log in the finished view. Good places to look for what could have gone wrong is the
  workflow.Rout file (which can be found under PEcAn Files pull down) or at the output from
  the model (which can be found under the Outputs pull down).
<?php } ?>
  <h2>Workflow Log</h2>
  Last <select id="loglines" onchange="refresh();">
<?php
$lines=array(10, 20, 50, 100);
foreach($lines as &$v) {
  if ($v == $loglines) {
    echo "<option selected>${v}</option>";
  } else {
    echo "<option>${v}</option>";
  }
}
?>
  </select> lines of the workflow.Rout
  <div class="logfile">
<?php
  $lines=array();
  if (file_exists("$folder/workflow2.Rout")) {
    $fp = fopen("$folder/workflow2.Rout", "r");
    while(!feof($fp)) {
      $line = htmlentities(fgets($fp, 4096));
      array_push($lines, $line);
      if (count($lines) > $loglines) {
        array_shift($lines);
      }
    }
    fclose($fp);
  }
  if (file_exists("$folder/workflow.Rout")) {
    $fp = fopen("$folder/workflow.Rout", "r");
    while(!feof($fp)) {
      $line = htmlentities(fgets($fp, 4096));
      array_push($lines, $line);
      if (count($lines) > $loglines) {
        array_shift($lines);
      }
    }
    fclose($fp);
  }
  echo implode("<br/>\n", $lines);
?>
  </div>
  </div>
  <div id="footer"><?php echo get_footer(); ?></div>
</div>
</body>
</html>

<?php 
function status($token) {
  global $folder;
  global $status;

  foreach ($status as $line) {
    $data = explode("\t", $line);
    if ($data[0] == $token) {
      if (count($data) >= 4) {
        return $data[3];
      }
      if ($token == "MODEL") {
    foreach(scandir("$folder/out") as $runid) {
      if (!is_dir("$folder/out/$runid") || ($runid == ".") || ($runid == "..")) {
        continue;
      }
      if (file_exists("$folder/out/$runid/logfile.txt")) {
        $running = "$runid - " . exec("awk '/Simulating/ { print $3 }' $folder/out/$runid/logfile.txt | tail -1");
      }
    }
    return $running;
      }
      return "Running";
    }
  }
  return "";
}
?>
