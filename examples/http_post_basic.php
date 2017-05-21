<?php

/*
LazyCurl v1.0 (2017-05-21)
Example performing a simple http post request.
*/

error_reporting(E_ALL);
$self = (isset($_SERVER["HTTPS"]) ? "https://" : "http://").$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
$title = trim(substr($self, strrpos($self, "/")), "/");
$url = substr($self, 0, strrpos($self, "/"))."/targets/http_post.php";

if (function_exists("curl_version")) { $curl_version = curl_version(); $curl_version = $curl_version["version"]; } else { $curl_version = "disabled"; }


require_once("../lazycurl.php");

echo "<head><title>{$title}</title></head>\r\n";
echo "<style type='text/css'>\r\n";
echo "body, div, span { font-size:12px; font-family:consolas; white-space:pre-wrap; line-height:15px; }\r\n";
echo "body { margin:0px; padding:20px 0px 0px 0px; background-color:rgb(20,20,20); }\r\n";
echo "div { margin:3px 50px; padding:3px 5px; border:1px dashed silver; overflow:auto; max-height:300px; background-color:white; }\r\n";
echo "span { color:green; }\r\n";
echo "a { color:teal; }\r\n";
echo "fieldset { margin:0px 20px 10px 20px; padding:10px 20px; border:1px solid green; }\r\n";
echo "</style>\r\n";

# Environment

	echo "<fieldset style='background-color:rgb(".mt_rand(230,250).",".mt_rand(230,250).",".mt_rand(230,250).");'>\r\n";
	echo "<b><span># ENVIRONMENT (this server)</span></b>\r\n";
	echo "<div>";
	echo "PHP            : ".phpversion()."\r\n";
	echo "Curl Library   : ".$curl_version."\r\n";
	echo "File Cookie    : ".(is_dir("./cookies") ? (is_writable("./cookies") ? "'./cookies' is ready for use" : "no write permission to access './cookies'") : "missing sub-directory './cookies'")."\r\n";
	echo "Temp Directory : ".(is_dir("./temp") ? (is_writable("./temp") ? "'./temp' is ready for use" : "no write permission to access './temp'") : "missing sub-directory './temp'")."\r\n";
	echo "</div>\r\n";
	echo "</fieldset>\r\n";

# First Attempt

	echo "<fieldset style='background-color:rgb(".mt_rand(230,250).",".mt_rand(230,250).",".mt_rand(230,250).");'>\r\n";
	echo "<b><span># CREATE OBJECT</span></b>\r\n";
	echo "<b>\$curl = new LazyCurl();</b>\r\n";
	$curl = new LazyCurl();
	echo "\r\n";

	echo "<b><span># PREPARE AN ARRAY OF POST DATA (multi-dimensional array is supported)</span></b>\r\n";
	echo "<b>\$user_detail =</b>\r\n";
	$id_a = strval(mt_rand(100,999));
	$id_b = strval(mt_rand(100,999));
	$user_detail = array(
		array(
			"ID" => $id_a,
			"Name" => "User A",
			"Email" => "user_a{$id_a}@example.com",
			"Alternate Email" => array("a{$id_a}_backup@example.com", "a{$id_a}_recovery@example.com"),
			"Phone" => array(
				"Mobile" => "{$id_a}0001",
				"Home" => array("Tel" => "{$id_a}0002", "Fax" => "{$id_a}0003"),
				"Office" => array("{$id_a}0004", "{$id_a}0005", "{$id_a}0006")
			),
			"Birthday" => array("Year" => 1998, "Month" => 10, "Day" => 5),
			"Gender" => "Female"
		),
		array(
			"ID" => $id_b,
			"Name" => "User B",
			"Email" => "user_b{$id_b}@example.com",
			"Phone" => array(
				"Mobile" => "{$id_b}7779",
				"Home" => array("Tel" => "{$id_b}7778")
			),
			"Birthday" => array("Year" => 1996, "Month" => 2, "Day" => 17),
			"Gender" => "Not Specified"
		),
	);
	echo "<div>";
	print_array($user_detail);
	echo "</div>\r\n";

	echo "<b><span># SENDING HTTP POST REQUEST TO URL</span></b>\r\n";
	echo "<b>\$curl->exec('{$url}', 'POST', \$user_detail);</b>\r\n";
	$curl->exec($url, "POST", $user_detail);
	echo "\r\n";

	echo "<b><span># GET RESPONSE FROM URL (basically showing everything received by target)</span></b>\r\n";
	echo "<b>\$curl->get_data();</b>\r\n";
	echo "<div>".trim($curl->get_data())."</div>\r\n";

	echo "<b><span># LOG RELATED TO THIS REQUEST</span></b>\r\n";
	echo "<b>\$curl->get_log();</b>\r\n";
	echo "<div>";
	print_array($curl->get_log());
	echo "</div>\r\n";

	echo "<b><span># CLOSE A CURL SESSION AND RELEASE MEMORY</span></b>\r\n";
	echo "<b>\$curl->close();</b>\r\n";
	$curl->close();
	echo "\r\n";
	echo "</fieldset>\r\n";

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
