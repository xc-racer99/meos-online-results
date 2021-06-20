<?php
if (1) {
	die("Not enabled!");
}

include_once('data.php');

$xml = new SimpleXMLElement('<MOPComplete xmlns="http://www.melin.nu/mop"><org id="1" nat="CAN">Sage</org></MOPComplete>');
$comp = $xml->addChild('competition');
$comp->addAttribute('date', $date);
$comp->addAttribute('organizer', 'Sage');
if (!empty($homepage)) {
	$comp->addAttribute('homepage', $homepage);
}
$comp[0] = $name;

/* Courses format is name, id, file */
foreach ($courses as $course) {
	$cls = $xml->addChild('cls');
	$cls->addAttribute('id', $course[1]);
	$cls->addAttribute('ord', $course[1]);
	$cls[0] = $course[0];

	$courseXml = simplexml_load_file($course[2]) or die('Failed to load course');

//	print_r($courseXml);
	$radios = "";

	foreach ($courseXml->RaceCourseData[0]->Course[0]->CourseControl as $pt) {
		if ($pt && $pt['type'] == 'Control') {
			$radios .= $pt->Control[0] . ",";
			$ctrl = $xml->addChild('ctrl');
			$ctrl->addAttribute('id', $pt->Control[0]);
			$ctrl[0] = $pt->Control[0];
		}
	}

	// remove tailing comma
	$radios = substr($radios, 0, -1);

	$cls->addAttribute('radio', $radios);

	/* Create folder for uploads */
	if (!file_exists('uploads/' . $course[1])) {
		mkdir('uploads/' . $course[1], 0775, true);
	}
}

//print_r($xml->asXml());

include_once('../update-common.php');

processXML($xml, $cmpId);

print_r($xml->asXml());

?>
