<!DOCTYPE html>
<html>
<head>
<link href="/style.css" rel="stylesheet" type="text/css" />
<?php
include_once('data.php');

echo "<title>" . $name . "</title>";
?>
<style>
.input-group label { width: 200px; display: inline-block; }
.input-group input { margin-bottom: 5px; display: inline-block; }
</style>
</head>

<?php

if (isset($_POST['submit'])) {

if (empty($_FILES['fileToUpload']['name'])) {
	die('No GPX provided');
}

if (empty($_POST['course'])) {
	print_r($_POST);
	die('No course selected');
}

if (empty($_POST['name'])) {
	die('No name set');
}

$selCourse = 0;

/* Find course (name, id file) */
foreach ($courses as $course) {
	if ($_POST['course'] == $course[1]) {
		$selCourse = $course;
	}
}

if ($selCourse === 0) {
	die('Invalid course selected');
}

$controls = array();
$name = $_POST['name'];

$courseXml = simplexml_load_file($selCourse[2]) or die('Failed to load course');

//print_r($courseXml);

$trkseg = $courseXml->trk[0]->trkseg[0];
if (!$trkseg) {
	die('Failed to parse course - contact website@skilarchhills.ca');
}

foreach ($trkseg->trkpt as $pt) {
	$controls[] = array($pt->name, $pt['lat'], $pt['lon']);
}

//print_r($controls);

$target_dir = "uploads/" . "$selCourse[1]/";
$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
$target_file = $target_dir . $name . '.gpx';

// Check if file already exists
if (file_exists($target_file)) {
    die("Sorry, file already exists - try a different name.");
}

// Check file size (5MB)
if ($_FILES["fileToUpload"]["size"] > 5000000) {
    die("Sorry, your file is too large.");
}

// Allow certain file formats
if($imageFileType != "gpx") {
    die("Sorry, only GPX files are allowed.");
}
if (!move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
    die("Sorry, there was an error uploading your file.");
}

$gpx = simplexml_load_file($target_file) or die("Failed to load uploaded GPX");

//var_dump($gpx);

$trkseg = $gpx->trk[0]->trkseg[0];
if (!$trkseg) {
    die("Failed to parse uploaded GPX - part 1");
}
if (!$trkseg->trkpt[0]) {
    die("Failed to parse uploaded GPX - part 2");
}
if (!$trkseg->trkpt[0]->time[0]) {
    die("Failed to parse uploaded GPX - part 3");
}

// Save first point's start time, in case we can't find actual start control
$startTime = $trkseg->trkpt[0]->time[0];

$lastIndex = 0;
$found = array();
$ok = 1;
$i = 0;

foreach ($controls as $control) {
	$i = $lastIndex;
	$pt = $trkseg->trkpt[$i];
	while ($pt = $trkseg->trkpt[$i++]) {

		// $control is name, lat, lon
		$latdiff = (float)$control[1] - (float)$pt['lat'];
		$londiff = (float)$control[2] - (float)$pt['lon'];

		if (abs($latdiff) > 0.00027 || abs($londiff) > 0.00044) {
			continue;
		}

		$found[] = array($control[0], $pt->time[0]);
		$lastIndex = $i;

		// Check if start
		if ($control === $controls[0]) {
			$startTime = $pt->time[0];
		}

		break;
	}

	if (!$pt) {
		$ok = 0;
	}
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

if ($ok) {
	$base->addAttribute('stat', '1');
} else {
	$base->addAttribute('stat', '3');
}

// Create datetime for start
$startDT = date_create($startTime);
$st = ($startDT->format("H") * 3600 + $startDT->format("i") * 60 + $startDT->format("s")) * 10;
$base->addAttribute('st', $st);

// End
$end = end($found);
if (strstr($end[0], "FIN")) {
	$time = date_create($end[1]);
	$diff = date_diff($time, $startDT);

	$rt = ($diff->h * 3600 + $diff->i * 60 + $diff->s) * 10;

	$base->addAttribute('rt', $rt);
} else {
	$base->addAttribute('rt', '0');
}
reset($found);

$radio = $cmp->addChild('radio');
$radios = "";

foreach ($found as $entry) {
	// $entry is control, time
	if (strstr($entry[0], "STA") || strstr($entry[0], "FIN") || empty($entry[0])) {
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

?>
<body>
<p>Uploaded, please check out <a href="https://results.sageorienteering.ca/?cmp=<?php echo $cmp; ?>">https://results.sageorienteering.ca</a></p>
</body>
</html>

<?php
include_once('../update-common.php');
processXML($mopdiff, $cmpId);

} else {
include_once("data.php");
?>
<body>
<h1><?php echo $name;?> Results Upload</h1>
<form action="results.php" method="post" enctype="multipart/form-data">
<div class="input-group">
    <label for="name">Name: </label>
    <input type="text" name="name" id="name">
</div>
<div class="input-group">
    <label for="fileToUpload">Select GPX to upload: </label>
    <input type="file" name="fileToUpload" id="fileToUpload">
</div>
<div class="input-group">
    <label for="course">Choose course</label>
    <select id="course" name="course">
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

