<?php

/*
LazyCurl v1.0 (2017-05-21)
Sample target for http post examples.
Note:
	This script is designed to show data received from a http post request.
*/

error_reporting(E_ALL);
$image_width = (!empty($_POST["Size"]["Width"])) ? $_POST["Size"]["Width"] : "100px";
$image_height = (!empty($_POST["Size"]["Height"])) ? $_POST["Size"]["Height"] : "100px";
$random_image = (!empty($_FILES["Image"]["tmp_name"]) && ($_FILES["Image"]["size"] > 0)) ? base64_encode(file_get_contents($_FILES["Image"]["tmp_name"])) : null;
$random_image_type = (!empty($_FILES["Image"]["type"])) ? $_FILES["Image"]["type"] : "image/jpeg";
$static_image = (!empty($_FILES["Local_Image"]["tmp_name"]) && ($_FILES["Local_Image"]["size"] > 0)) ? base64_encode(file_get_contents($_FILES["Local_Image"]["tmp_name"])) : null;
$static_image_type = (!empty($_FILES["Local_Image"]["type"])) ? $_FILES["Local_Image"]["type"] : "image/jpeg";

header("Content-Type: text/plain", true);
if (!empty($static_image)) { echo "<span style='float:right; width:185; height:240; background:url(data:{$static_image_type};base64,{$static_image}) top right no-repeat;'></span>"; }
if (!empty($random_image)) { echo "<span style='float:right; width:{$image_width}; height:{$image_height}; background:url(data:{$random_image_type};base64,{$random_image}) top right no-repeat;'></span>"; }
if (!empty($_POST)) { print_array($_POST); }

function print_array($array) {
	# Output Array With Indication Of Data Type
	$str = var_export($array, true);
	$str = preg_replace("/^(\s*)'([^'\r\n]+)'(?=\s+=>)/ims", "$1$2", $str);														# remove bounding quote in array key
	$str = preg_replace("/^(\s*)([^'\r\n]+)(?=\s+=>)/ims", "$1[$2]", $str);														# add square brackets to array key
	$str = preg_replace("/^(\s*)(?=\S)/ims", "$1$1", $str);																		# double indent size
	$str = preg_replace("/(\s*)array\s+\(/ims", " Array$1(", PHP_EOL.$str);														# reposition array
	$str = preg_replace("/(\s*)stdclass::__set_state\(\s*array\s*\(/ims", " stdClass::__set_state( Array$1(", $str);			# reposition object
	$lines = explode(PHP_EOL, trim($str));
	$str = ""; $tab = -1;
	foreach ($lines as $line) {
		$byte = substr((trim($line)), 0, 1);
		if ($byte == "(") { $tab++; }
		$str .= str_pad("", $tab * 4, " ").trim($line, ",").PHP_EOL;
		if ($byte == ")") { $tab--; }
	}
	echo $str;
}

?>

