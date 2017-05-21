<?php

/*
LazyCurl v1.0 (2017-05-21)
Example performing http post request with file upload.
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

	echo "<b><span># PREPARE AN ARRAY OF POST DATA (using @ prefix with local file and url to download image before http post request)</span></b>\r\n";
	echo "<b>\$image_detail =</b>\r\n";
	$width = mt_rand(250,350);
	$height = mt_rand(100,200);
	$download_url = "https://unsplash.it/{$width}/{$height}/?random";
	$image_detail = array(
		"Description" => "Images provided by <a href=\"https://unsplash.com/\" target='_blank'>Unsplash</a>",
		"Size" => array("Width" => "{$width}px", "Height" => "{$height}px"),
		"Image" => "@{$download_url}\tfilename=random_image.jpg",
		"Local Image" => "@./images/codes.jpg",
		"Connect With Unsplash" => array("/unsplash on <a href=\"https://www.facebook.com/unsplash/\" target='_blank'>Facebook</a>", "@unsplash on <a href=\"https://www.instagram.com/unsplash/\" target='_blank'>Instagram</a>", "/unsplash on <a href=\"https://www.pinterest.com/unsplash/\" target='_blank'>Pinterest</a>", "@unsplash on <a href=\"https://twitter.com/unsplash/\" target='_blank'>Twitter</a>"),
		"Note" => "Random image is stream downloaded from <a href=\"https://unsplash.it/\" target='_blank'>Unsplash It</a>. It is a free service and response may be slow sometimes.",
	);
	echo "<div>";
	print_array($image_detail);
	echo "</div>\r\n";

	echo "<b><span># COPY EXACT SAME STRINGS WITH @ PREFIX TO ANOTHER ARRAY (as an indication of file upload in http post request)</span></b>\r\n";
	echo "<b>\$image_manifest =</b>\r\n";
	$image_manifest = array($image_detail["Image"], $image_detail["Local Image"]);
	echo "<div>";
	print_array($image_manifest);
	echo "</div>\r\n";

	echo "<b><span># SENDING HTTP POST REQUEST TO URL</span></b>\r\n";
	echo "<b>\$curl->exec('{$url}', 'POST', \$image_detail, \$image_manifest);</b>\r\n";
	$curl->exec($url, "POST", $image_detail, $image_manifest);
	echo "\r\n";

	echo "<b><span># GET RESPONSE FROM URL (basically showing everything received by target including @ prefix text strings and uploaded images)</span></b>\r\n";
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
