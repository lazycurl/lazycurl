<?php

/*
LazyCurl v1.0 (2017-05-21)
Sample target for http get examples.
Note:
	This script is designed to demonstrate how cookie works between multiple http requests.
	It generates a cookie (unix timestamp with micro seconds) which will be expired in 300 seconds.
	When this script is called again before cookie expiry, the receiving cookie (sent with request) will be displayed.
	The timestamp difference between previous and current requests will be included in the new cookie.
*/

error_reporting(E_ALL);
$cookie_name = "sample_cookie";
$cookie_expire = 300;
$prev_cookie = null;

$prev_time = $this_time = microtime(true);
if (isset($_COOKIE[$cookie_name])) {
	if ($prev_cookie = strstr($_COOKIE[$cookie_name], " ", true)) {
		if (is_numeric($prev_cookie)) { $prev_time = floatval($prev_cookie); }
	}
}
$cookie_value = sprintf("%.4f", $this_time)." (+".sprintf("%.4f", $this_time - $prev_time)."s)";

setcookie(
	$cookie_name,																		# name (case sensitive)
	$cookie_value,																		# value (anything except whitespace, comma & colon)
	time() + $cookie_expire,															# expiry (unix timestamp)
	substr($_SERVER["REQUEST_URI"], 0 , strrpos($_SERVER["REQUEST_URI"], "/") + 1),		# path (accessible within path & sub-folders, use "/" to allow access in any path)
	$_SERVER["SERVER_NAME"],															# domain (accessible within host & sub-domains, use empty string to limit access to exact match only)
	isset($_SERVER["HTTPS"]) ? true : false,											# secure (indicate if https is required)
	true																				# httponly (always true to prevent client-side access, e.g. javascript)
);

header("Content-Type: text/plain", true);
header("X-Sample: demonstrating ipv4 extraction, e.g. 192.168.1.100 and 10.0.0.20:80 are valid while 192.168.260.1 is not", true);
echo "Client IP         : [".$_SERVER["REMOTE_ADDR"]."]\r\n";
echo "Receiving Cookie  : [{$prev_cookie}]\r\n";
echo "Responding Cookie : [{$cookie_value}] expires in {$cookie_expire} seconds\r\n";

?>
