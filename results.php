<!DOCTYPE html>
<html>
<head>
<link href="/style.css" rel="stylesheet" type="text/css" />
<html>
<head>
<meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
        <title>Live Results</title>
        <link href="style.css" rel="stylesheet" type="text/css" />
        <!-- favicon -->
        <link rel="shortcut icon" href="images/favicon.png">

        <!-- Auto-refresh -->
        <!-- <meta http-equiv="refresh" content="120">-->
        </head>

        <body>
        <header>
        <img src="../images/SageLogo.png" alt="Sage Orienteering Club Live Results"/>
<div class="topnav">
<ul>
  <li><a href="https://results.sageorienteering.ca/">All Results</a></li>
  <li><a href="https://sage.whyjustrun.ca/">Sage Home</a></li>
  <li><a href="https://sage.whyjustrun.ca/pages/211">Help</a></li>
</ul>
</div>
<?php

function retry($error) {
echo "<p>" . $error . "</p>";
echo '<button onClick="window.location.href=window.location.href">Retry</button>';
echo "</body></html>";
exit();
}

include_once('data.php');

echo "<title>" . $name . "</title>";
?>
<style>
.input-group label { width: 200px; display: inline-block; }
.input-group input { margin-bottom: 5px; display: inline-block; }
</style>
</head>
<body>
<?php

if (isset($_POST['submit'])) {

if (empty($_FILES['fileToUpload']['name'])) {
	retry('No GPX provided');
}

if (empty($_POST['course'])) {
	print_r($_POST);
	retry('No course selected');
}

if (empty($_POST['name'])) {
	retry('No name set');
}

$selCourse = 0;

/* Find course (name, id file) */
foreach ($courses as $course) {
	if ($_POST['course'] == $course[1]) {
		$selCourse = $course;
	}
}

if ($selCourse === 0) {
	retry('Invalid course selected');
}

$controls = array();
$name = $_POST['name'];

$courseXml = simplexml_load_file($selCourse[2]) or retry('Failed to load course');

//print_r($courseXml);

$course = $courseXml->RaceCourseData[0]->Course[0];
if (!$course) {
	retry('Failed to parse course - contact contact@sageorienteering.ca');
}

foreach ($course->CourseControl as $pt) {
	if ($pt) {
		// Find the control, then find lat/long
		$found = false;
		$ctrl = array();

		foreach ($courseXml->RaceCourseData[0]->Control as $control) {
			if (strcmp($control->Id[0], $pt->Control[0]) == 0) {
				$ctrl[0] = $pt->Control[0];
				$ctrl[1] = $control->Position[0]['lat'];
				$ctrl[2] = $control->Position[0]['lng'];
				$ctrl[3] = false;
				$found = true;
				break;
			}
		}

		if (!$found) {
			retry('Invalid course file - contact contact@sageorienteering.ca');
		}

		if (strcmp($pt['type'], "Control") == 0) {
			// Find this start control and get the lat/long
			$ctrl[4] = $pt->Score;
		} else if (strcmp($pt['type'], "Start") == 0) {
			$ctrl[0] = "Start";
		} else if (strcmp($pt['type'], "Finish") == 0) {
			$ctrl[0] = "Finish";
		}

		$controls[] = $ctrl;
	}
}

//print_r($controls);

$target_dir = "uploads/" . "$selCourse[1]/";
$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
$target_file = $target_dir . $name . '.gpx';

// Check if file already exists
if (file_exists($target_file)) {
    retry("Sorry, file already exists - try a different name.");
}

// Check file size (5MB)
if ($_FILES["fileToUpload"]["size"] > 5000000) {
    retry("Sorry, your file is too large.");
}

// Allow certain file formats
if($imageFileType != "gpx") {
    retry("Sorry, only GPX files are allowed.");
}
if (!move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
    retry("Sorry, there was an error uploading your file.");
}


$file = file_get_contents($target_file) or retry("Failed to load uploaded GPX");

preg_replace("/\<\/trkseg\>.*\<trkseg\>/", "", $file);

$gpx = simplexml_load_string($file) or retry("Failed to load uploaded GPX as XML");

//var_dump($gpx);

$trkseg = $gpx->trk[0]->trkseg[0];
if (!$trkseg) {
    retry("Failed to parse uploaded GPX - part 1");
}
if (!$trkseg->trkpt[0]) {
    retry("Failed to parse uploaded GPX - part 2");
}
if (!$trkseg->trkpt[0]->time[0]) {
    retry("Failed to parse uploaded GPX - part 3");
}

// Save first point's start time, in case we can't find actual start control
$startTime = $trkseg->trkpt[0]->time[0];
$finishTime = 0;

$lastIndex = 0;
$found = array();
$i = 0;
$pts = 0;

while ($pt = $trkseg->trkpt[$i++]) {
	// $control is name, lat, lon, found, pts
	foreach ($controls as &$control) {
		// Ensure we haven't found already
		if ($control[3]) {
			continue;
		}

		$latdiff = (float)$control[1] - (float)$pt['lat'];
		$londiff = (float)$control[2] - (float)$pt['lon'];

		if (abs($latdiff) > 0.00027 || abs($londiff) > 0.00044) {
			continue;
		}

		$found[] = array($control[0], $pt->time[0]);

		// Check if start
		if (strcmp($control[0], "Start") == 0) {
			if ($i < count($trkseg->trkpt) / 2) {
				$startTime = $pt->time[0];
				$control[3] = true;
			}
		} else if (strcmp($control[0], "Finish") == 0) {
			$finishTime = $pt->time[0];
		} else {
			// Mark that we've found this control
			$control[3] = true;
			$pts += $control[4];
		}
	}
}

// Set finish time as the end time
if ($finishTime === 0) {
	$finishTime = $trkseg->trkpt[count($trkseg->trkpt) - 1];
}

// Create MOPDdiff
$mopdiff = new SimpleXMLElement('<MOPDiff xmlns="http://www.melin.nu/mop"></MOPDiff>');
$cmp = $mopdiff->addChild('cmp');
$dt = date_create();
//$str = $dt->format("y") . $dt->format("m") . $dt->format("d") . $dt->format("H") . $dt->format("i") . $dt->format("s");
$str = $dt->format("H") . $dt->format("i") . $dt->format("s");
$cmp->addAttribute('id', $str);
$base = $cmp->addChild('base');
$base[0] = $name;
$base->addAttribute('org', '1');
$base->addAttribute('cls', $selCourse[1]);
$base->addAttribute('stat', '1');

// Create datetime for start
$startDT = date_create($startTime);
$st = ($startDT->format("H") * 3600 + $startDT->format("i") * 60 + $startDT->format("s")) * 10;
$base->addAttribute('st', $st);

// Create datetime for finish
$finishDT = date_create($finishTime);
$totalDT = date_diff($finishDT, $startDT);
$rt = ($totalDT->h * 3600 + $totalDT->i * 60 + $totalDT->s) * 10;
$base->addAttribute('rt', $rt);

//echo $finishTime . "<br />";
//echo $startTime . "<br />";
//var_dump($totalDT);

// Calculate deductions
$secondsOver = ($rt / 10) - $selCourse[3];
if ($secondsOver > 0) {
	$deductionCount = $point_deduction_per_minute * ((int)($secondsOver / 60) + 1);
} else {
	$deductionCount = 0;
}
$pts -= $deductionCount;

$radio = $cmp->addChild('radio');
$radios = "";

foreach ($found as $entry) {
	// $entry is control, time
	if ($entry[0] == "Start" || $entry[0] == "Finish" || empty($entry[0])) {
		continue;
	}

	$time = date_create($entry[1]);
	$diff = date_diff($time, $startDT);

	$rtd = ($diff->h * 3600 + $diff->i * 60 + $diff->s) * 10;

	$radios .= $entry[0] . "," . $rtd . ";";
}
$radios = substr($radios, 0, -1);
$radio[0] = $radios;

//echo $mopdiff->asXml();

// Update text file
file_put_contents($target_dir . "results.txt", $name . "," . $totalDT->h . ":" . $totalDT->i . ":" . $totalDT->s . "," . $pts . ",-" . $deductionCount . "\n", FILE_APPEND);

?>
<p>Uploaded, please check out <a href="https://results.sageorienteering.ca/?cmp=<?php echo $cmpId; ?>">https://results.sageorienteering.ca</a></p>
</body>
</html>

<?php
include_once('../update-common.php');
processXML($mopdiff, $cmpId);

} else {
include_once("data.php");
?>
<h1><?php echo $name;?> Results Upload</h1>
<form action="results.php" method="post" enctype="multipart/form-data">
<div class="input-group">
    <label for="name">Participant Name: </label>
    <input type="text" name="name" id="name" maxlength="24">
</div>
<div class="input-group">
    <label for="fileToUpload">Select GPX to upload: </label>
    <input type="file" name="fileToUpload" id="fileToUpload">
</div>
<div class="input-group">
    <label for="course">Choose course</label>
    <select id="course" name="course">
        <option disabled selected value> -- select a course -- </option>
<?php
foreach ($courses as $course) {
	echo '<option value="' . $course[1] . '">' . $course[0] . "</option>";
}
?>
    </select>
</div>
<div class="input-group">
    <input type="submit" value="Upload GPX" name="submit">
</div>
</form>

</body>
</html>


<?php
} /* No file uploaded */

