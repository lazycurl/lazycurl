<?php

/*
LazyCurl v1.0 (2017-05-27)
Test script for travis.
*/



error_reporting(E_ALL);
require_once("lazycurl.php");
$curl = new LazyCurl();
$exit = 1111;



$curl_version = curl_version();
$scheme = (version_compare($curl_version["version"], "7.34.0", ">=")) ? "https" : "http";
if (!file_exists("./temp")) { mkdir("./temp", 0755); }



echo ">> Test 1 : {$scheme} get with response test... ";
$check = false;
$key = "x-".strval(mt_rand(1000, 9999));
$value = md5($key);
$curl->exec("{$scheme}://httpbin.org/redirect-to?url=".urlencode("{$scheme}://httpbin.org/response-headers?{$key}={$value}"));
$http_var = $curl->get_var();
foreach ($http_var as $response_headers) {
	foreach ($response_headers as $header_key => $header_value) {
		if (($header_key === $key) && ($header_value === $value)) { $check = true; break; }
	}
	if ($check) { break; }
}
if ($check) { $exit -= 1000; echo "OK"; } else { echo "FAIL"; }
echo "\r\n";



echo ">> Test 2 : {$scheme} get with cookie test... ";
$check = false;
$key = mt_rand(1000000, 9999999);
$value = md5($key);
$curl->session_cookie(true);
$curl->exec("{$scheme}://httpbin.org/cookies/set?{$key}={$value}");
$cookies = $curl->get_cookie();
foreach ($cookies as $cookie) {
	if (($cookie["name"] === strval($key)) && ($cookie["value"] === strval($value))) { $check = true; break; }
}
if ($check) { $exit -= 100; echo "OK"; } else { echo "FAIL"; }
echo "\r\n";



echo ">> Test 3 : {$scheme} post with @-prefix... ";
$check = false;
$curl->set_opt(array("CURLOPT_CONNECTTIMEOUT" => 30, "CURLOPT_TIMEOUT" => 90));
$data = array(
	"test" => array("index" => 2, "description" => "{$scheme} post with @-prefix", "note" => array("this is a multi-dimensional array", "some of the @-prefix fields are text content", "some others @-prefix fields are file upload")),
	"example" => array(
		"file_list" => array(
			array("id" => 1, "url" => "@ftp://speedtest.tele2.net/1KB.zip\tfilename=1kb_dummy.zip", "description" => "1kb dummy zip file for speed test only"),
			array("id" => 2, "url" => "@{$scheme}://unsplash.it/".mt_rand(40,80)."/".mt_rand(40,80)."/?random\tfilename=random_image.jpg", "description" => "random image stream download from unsplash.it")
		),
		"mention_list" => array("family" => array("@mom", "@dad", "@sister", "@brother"), "friend" => array("@mickey", "@minnie", "@donald", "@daisy", "@goofy", "@pluto"), "colleague" => array("@huey", "@dewey", "@louie"))
	)
);
$file = array($data["example"]["file_list"][0]["url"], $data["example"]["file_list"][1]["url"]);
$curl->exec("{$scheme}://httpbin.org/post", "POST", $data, $file);
$response = json_decode($curl->get_data(), true);
if ((count($response["form"]) == 22) && (count($response["files"]) == 2)) { $check = true; }
if ($check) { $exit -= 10; echo "OK"; } else { echo "FAIL"; }
echo "\r\n";



echo ">> Test 4 : {$scheme} download + ftp upload... ";
$check = false;
$file_dl = $curl->download("{$scheme}://www.google.com/favicon.ico", "", "google.ico", false);
$file_ul = $curl->upload($file_dl, "ftp://speedtest.tele2.net/upload/", "favicon.ico", false);
$http_log = $curl->get_log();
if (($file_ul) && ($http_log["curlinfo"]["size_upload"] > 1024)) { $check = true; }
if ($check) { $exit -= 1; echo "OK"; } else { echo "FAIL"; }
echo "\r\n";



$curl->close();
exit($exit);

?>