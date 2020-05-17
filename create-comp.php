<?php
if (1) {
	die("Not enabled!");
}

include_once('data.php');

$xml = new SimpleXMLElement('<MOPComplete xmlns="http://www.melin.nu/mop"><org id="1" nat="CAN">Sage</org></MOPComplete>');
$comp = $xml->addChild('competition');
$comp->addAttribute('date', $date);
$comp->addAttribute('organizer', 'Sage');
$comp->addAttribute('homepage', 'https://sage.whyjustrun.ca');
$comp[0] = $name;

$cls = $xml->addChild('cls');
$cls->addAttribute('id', '1');
$cls[0] = "Open";

$courseXml = simplexml_load_file($courseFile) or die('Failed to load course');

//print_r($courseXml);
$radios = "";

$trkseg = $courseXml->trk[0]->trkseg[0];
if (!$trkseg) {
	die('Failed to parse course - contact website@skilarchhills.ca');
}

foreach ($trkseg->trkpt as $pt) {
	if (strstr($pt->name[0], "STA") || strstr($pt->name[0], "FIN") || empty($pt->name[0])) {
		continue;
	}

	$radios .= $pt->name[0] . ",";
	$ctrl = $xml->addChild('ctrl');
	$ctrl->addAttribute('id', $pt->name[0]);
	$ctrl[0] = $pt->name[0];
}

// remove tailing comma
$radios = substr($radios, 0, -1);

$cls->addAttribute('radio', $radios);

include_once('../update-common.php');

processXML($xml, $cmpId);

print_r($xml->asXml());

?>
