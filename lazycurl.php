<?php

/*
LazyCurl v1.0

~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
MIT License

Copyright (c) 2017 http://lazycurl.net/

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

History:
	2017-05-21. Initial release.

System Requirement:
	1. php v5.3.0+
	2. curl v7.19.4+ (preferably curl v7.24.0 for ca root certificate checking, curl v7.34.0 for better ssl security)
	3. fileinfo module (optional)

Note - Curl Option:
	1. CURLOPT_SAFE_UPLOAD, CURLOPT_PROTOCOLS, CURLOPT_RETURNTRANSFER, CURLOPT_HEADERFUNCTION and CURLINFO_HEADER_OUT are locked to ensure functionalities.
	2. Additional curl options can be set. It is required to use option name as string instead of constant for validation check.

Note - Cookie Handling:
	1. Session cookie is default disabled. It can be enabled only if file cookie is not activated.
	2. Received cookie headers will always be processed as read-only variables even when no cookie handling is enabled.
	3. Switching to file cookie (persistent) is supported if the cookie will be re-use in another process.
	4. When file cookie is activated, it is required to close the current session in order to update cookie file. Therefore switching back to session cookie is not allowed.

Note - Post Data:
	1. It is important to use an associative array for post data as parameter 3 when calling $this->exec(). It will be formatted as urlencoded string if file upload is not required.
	2. Multi-dimensional (nested) array is supported in http post request. The superglobal variable $_post in target server will be automatically restored to its original structure.

Note - @ Prefix Upload Compatibility:
	1. Use @ prefix with full absolute path for file upload, e.g. "@/tmp/image.png". Calling realpath() is recommended only for resolving symbolic links.
	2. Relative path is supported only if it is located within current directory, e.g. "@./images/example.jpg".
	3. Explicit file name and file type are not necessary but supported using "\t" (tab) as separator, e.g. "@/tmp/image.png\tfilename=my_avatar.png\ttype=image/png".
	4. It is important to add the exact same string to an array as parameter 4 when calling $this->exec(). The array is used as an indication of file upload operation.
	5. Any @ prefix string missing in parameter 4 when calling $this->exec() will be considered as posting a string value instead of file upload.
	6. CURLFile object will be used for file upload if php is v5.5.0+. For earlier php version, a space will be prepended when posting a string value which begins with "@".
	7. When using CURLFile object, mime type will be automatically detected if not specified. But php fileinfo module is required though.
	8. Using http / ftp url in @ prefix is supported, e.g. "@http://www.example.com/favicon.ico". Additional curl session will be created instead of using file_get_contents which can be limited by "allow_url_fopen".

Note - Direct Download:
	1. Downloading single file from http / ftp is supported. Streaming transfer is always used in order to support downloading large file with low memory consumption.
	2. It is recommended to specify a file name when downloading from dynamic link, e.g. "http://forum.example.com/attachment.php?file_id=734550".
	3. File name can be automatically extracted from either "Content-Disposition" header or the url itself. Otherwise, a temp file name will be used.
	4. Unix timestamp will be added to the end of file name if already exists, unless overwrite mode is specified.

Note - FTP Upload:
	1. Uploading single file to ftp is supported. Streaming transfer is always used in order to support uploading large file with low memory consumption.
	2. A different file name can be specified. Otherwise, local file name will be used.
	3. Unix timestamp will be added to the end of file name if already exists, unless overwrite mode is specified.
*/



class LazyCurl {



	private $ch = null;							# curl handle
	private $data = "";							# response from last curl execution
	private $location_idx = -1;					# location index key in case of redirection
	private $header_vars = array();				# variables extracted from response headers of last curl execution
	private $log = array();						# detail log of last curl execution
	private $session_cookie = false;			# default false to stop sending session cookie header but received cookie headers will still be processed as read-only variables
	private $cookies = array();					# array of all cookies
	private $options = array();					# curl options
	private $tmp_files = array();				# downloaded temp files that should be deleted after upload



	# check system requirements before initializing variables and session
	function __construct() {
		if (version_compare(phpversion(), "5.3.0", "<")) { trigger_error("php v5.3.0 or above is required", E_USER_ERROR); }
		if (!function_exists("curl_version")) { trigger_error("curl library is not enabled", E_USER_ERROR); }
		$this->init();
	}



	# main curl execution
	#		@param string	$url				target url
	#		@param string	$method				request method such as "GET", "POST" or any other method supported by target server
	#		@param array	$fields				http post data to be sent except http get request
	#		@param array	$manifest			list of files to be uploaded except http get request
	#		@return void
	public function exec($url, $method = "GET", $fields = array(), $manifest = array()) {
		if (!is_resource($this->ch)) {								# automatically initialize curl session if it is closed
			$this->init();
			if (!is_resource($this->ch)) { trigger_error("curl session cannot be initialized", E_USER_ERROR); }										# stop processing if curl session cannot be initialized
		}
		elseif (!is_array($fields)) { trigger_error("exec() expects parameter 3 to be associative array", E_USER_ERROR); }							# array is required
		elseif (!is_array($manifest)) { trigger_error("exec() expects parameter 4 to be array", E_USER_ERROR); }									# array is required
		# initialize variables for current request
		$this->data = "";
		$this->location_idx = -1;
		$this->header_vars = $this->tmp_files = array();
		$this->log = array("host" => null, "summary" => null, "request" => array(), "response" => array(), "set-cookie" => array(), "curlinfo" => array());
		# handling request options
		$req_opt = array("CURLOPT_URL" => $url);
		if (strtoupper($method) == "GET") {
			if (isset($this->options["CURLOPT_NOBODY"]) && $this->options["CURLOPT_NOBODY"]) { $req_opt["CURLOPT_CUSTOMREQUEST"] = "HEAD"; }
			else { $req_opt["CURLOPT_HTTPGET"] = true; }
		}
		else {
			if (strtoupper($method) == "POST") { $req_opt["CURLOPT_POST"] = true; }
			else { $req_opt["CURLOPT_CUSTOMREQUEST"] = $method; }
			if (empty($manifest)) {																													# fields will be encoded as application/x-www-form-urlencoded
				$req_opt["CURLOPT_POSTFIELDS"] = (defined("PHP_QUERY_RFC3986")) ? http_build_query($fields, "", "&", PHP_QUERY_RFC3986) : http_build_query($fields, "", "&");
			}
			else {																																	# fields will be encoded as multipart/form-data
				$flatten_fields = $this->array_flatten_recursive($fields);
				array_walk_recursive($flatten_fields, array($this, "field_validation"), $manifest);
				$req_opt["CURLOPT_POSTFIELDS"] = $flatten_fields;
			}
		}
		$this->set_opt($req_opt);
		# send request
		$this->data = curl_exec($this->ch);
		if (curl_errno($this->ch)) { $this->data = curl_error($this->ch); }
		# reset referer
		if (isset($this->options["CURLOPT_REFERER"])) { $this->set_opt(array("CURLOPT_REFERER" => null)); }
		# delete temp files
		foreach ($this->tmp_files as $tmp_file) {
			if (file_exists($tmp_file)) { unlink($tmp_file); }
		}
		# consolidate detail log for current execution
		$curlinfo = curl_getinfo($this->ch);
		$this->log["host"] = (isset($curlinfo["primary_ip"])) ? $curlinfo["primary_ip"] : gethostbyname(parse_url($curlinfo["url"], PHP_URL_HOST));
		$this->log["request"] = (isset($curlinfo["request_header"])) ? array_map("trim", explode(PHP_EOL, $this->hide_credential(trim($curlinfo["request_header"])))) : array();
		foreach ($curlinfo as $key => $value) {
			if (in_array($key, array("http_code", "header_size", "request_size", "total_time", "namelookup_time", "connect_time", "pretransfer_time", "size_upload", "size_download", "speed_download", "speed_upload", "starttransfer_time"))) {
				if (is_array($value)) { $value = json_encode($value); }
				elseif (is_numeric($value) && (stripos($key, "_time") !== false)) { $value = sprintf("%f", $value); }
				$this->log["curlinfo"][$key] = $value;
			}
		}
		# generate a single line summary
		$summary = array(
			"utc" => substr($this->now(), 11, 8),
			"host" => $this->log["host"],
			"time" => number_format($curlinfo["total_time"], 2),																															# excluding @ prefix download
			"size" => number_format(($curlinfo["size_upload"] + $curlinfo["size_download"]) / 1024, 2)."k",																					# excluding @ prefix download
			"speed" => (($curlinfo["total_time"] == 0) ? "0.00" : number_format(($curlinfo["size_upload"] + $curlinfo["size_download"]) / $curlinfo["total_time"] / 1024, 2))."k/s",		# overall performance
		);
		foreach ($summary as $key => $value) { $this->log["summary"] .= "[{$key}:{$value}]"; }
	}



	# http / ftp stream download
	#		@param string	$url				target url, may include username / password such as ftp://user:pass@example.com/path/file.ext
	#		@param string	$local_path			absolute local path to save the downloaded file
	#		@param string 	$local_name			specific file name to be used for downloaded file
	#		@param boolean	$overwrite			default false to rename file by adding current unix timestamp, or set true to overwrite existing file
	#		@return string						full local path with actual file name of the downloaded file, or false on error
	public function download($url, $local_path = "", $local_name = "", $overwrite = false) {
		if (!preg_match("/^(https?|ftp):\/\//ims", $url)) { trigger_error("download() expects parameter 1 to be http / ftp url", E_USER_WARNING); }
		else {
			# initialize variables for current request
			$new_file = null;
			$tmp_file = tempnam("temp", "lc_");
			$tmp_fp = fopen($tmp_file, "w");
			# temporary curl options for downloading file
			$old_options = array_merge(array("CURLOPT_FILE" => fopen("php://stdout", "w")), $this->options);
			$tmp_options = array("CURLOPT_CONNECTTIMEOUT" => 30, "CURLOPT_TIMEOUT" => 600, "CURLOPT_FILE" => $tmp_fp);
			$this->set_opt($tmp_options);
			$this->exec($url);
			$http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
			$tmp_type = curl_getinfo($this->ch, CURLINFO_CONTENT_TYPE);
			fclose($tmp_fp);
			$this->set_opt($old_options);
			if ($http_code >= 400) { trigger_error("server response {$http_code} when trying to access '".$this->hide_credential($url)."'", E_USER_WARNING); }
			else {
				# find the best matching local path for downloaded file
				if (empty($local_path)) { $local_path = rtrim(getcwd(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."temp"; }
				else {
					$local_path = rtrim($local_path, DIRECTORY_SEPARATOR);
					if (substr($local_path, 0, 2) == ".".DIRECTORY_SEPARATOR) { $local_path = rtrim(getcwd(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.substr($local_path, 2); }			# fix relative path
				}
				# find the best matching file name for downloaded file
				if (empty($local_name)) {
					if (!empty($this->header_vars[$this->location_idx]["content-disposition"])) {
						preg_match("/filename\s*=\s*([^;]+)[;\s]/imsU", $this->header_vars[$this->location_idx]["content-disposition"], $matches);
						if (!empty($matches[1])) { $local_name = trim($matches[1], " '\""); }									# (name + ext) first priority in http header
					}
					if (empty($local_name)) {
						$pathinfo = pathinfo(parse_url($url, PHP_URL_PATH));
						if (!empty($pathinfo["filename"])) { $local_name = $pathinfo["filename"]; }								# (name) failover to file name in target url
						else { $local_name = pathinfo($tmp_file, PATHINFO_FILENAME); }											# (name) lastly use temp file name
						if (!empty($pathinfo["extension"])) { $local_name .= ".".$pathinfo["extension"]; }						# (ext) find file extension in target url
						elseif (!empty($tmp_type)) { $local_name .= ".".str_replace("/", ".", $tmp_type); }						# (ext) failover to content type in http header as descriptive extension
					}
				}
				# move file only if file has content
				if (filesize($tmp_file) > 0) {
					$new_file = $local_path.DIRECTORY_SEPARATOR.$local_name;
					if (file_exists($new_file) && !$overwrite) {																# add unix timestamp to the end of file name
						$pathinfo = pathinfo($new_file);
						$local_name = $pathinfo["filename"]."_".time().((!empty($pathinfo["extension"])) ? ".".$pathinfo["extension"] : "");
						$new_file = $local_path.DIRECTORY_SEPARATOR.$local_name;
					}
					if (!$this->is_writable($new_file)) { trigger_error("no write permission to access '{$new_file}'", E_USER_WARNING); }
					else {
						if (is_resource($tmp_fp)) { fclose($tmp_fp); }															# workaround for php 5.3.0 on windows
						rename($tmp_file, $new_file);
						chmod($new_file, 0644);
					}
				}
			}
			if (file_exists($tmp_file) && ($tmp_file != $new_file)) { unlink($tmp_file); }
			if (file_exists($new_file) && $this->is_writable($new_file)) {
				$this->data = "";
				return $new_file;
			}
		}
		$this->data = "";
		return false;
	}



	# ftp stream upload
	#		@param string	$file				absolute local path with file name to be uploaded
	#		@param string	$remote_path		target ftp with path, may include username / password such as ftp://user:pass@example.com/path/
	#		@param string 	$remote_name		specific file name to be used for uploaded file
	#		@param boolean	$overwrite			default false to rename file by adding current unix timestamp, or set true to overwrite existing file
	#		@return string						full remote path (username / password will be masked) with actual file name of the uploaded file, or false on error
	public function upload($file, $remote_path, $remote_name = "", $overwrite = false) {
		if (substr($file, 0, 2) == ".".DIRECTORY_SEPARATOR) { $file = rtrim(getcwd(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.substr($file, 2); }						# fix relative path
		if (empty($file)) { trigger_error("an accessible file is required for uploading", E_USER_WARNING); }
		elseif (!file_exists($file)) { trigger_error("'{$file}' is missing", E_USER_WARNING); }
		elseif (is_dir($file)) { trigger_error("'{$file}' is a directory", E_USER_WARNING); }
		elseif (!is_readable($file)) { trigger_error("no read permission to access '{$file}'", E_USER_WARNING); }
		elseif (!preg_match("/^ftp:\/\//ims", $remote_path)) { trigger_error("upload() expects parameter 2 to be ftp url for uploading", E_USER_WARNING); }				# ftp only, http upload is a post request
		else {
			# temporary curl options for checking file list in remote path
			$remote_path = rtrim($remote_path, "/")."/";
			$old_options = array_merge(array("CURLOPT_FTPLISTONLY" => false), $this->options);
			$tmp_options = array("CURLOPT_FTPLISTONLY" => true);
			$this->set_opt($tmp_options);
			$this->exec($remote_path);
			$http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
			$this->set_opt($old_options);
			if ($http_code >= 400) { trigger_error("server response {$http_code} when trying to access '".$this->hide_credential($remote_path)."'", E_USER_WARNING); }
			else {
				# find the best name for uploaded file
				if (empty($remote_name)) { $remote_name = pathinfo($file, PATHINFO_BASENAME); }
				$remote_list = preg_split("/\s+/", $this->data);
				if (in_array($remote_name, $remote_list) && !$overwrite) {														# add unix timestamp to the end of file name
					$pathinfo = pathinfo($remote_name);
					$remote_name = $pathinfo["filename"]."_".time().((!empty($pathinfo["extension"])) ? ".".$pathinfo["extension"] : "");
				}
				# temporary curl options for uploading file
				$url = $remote_path.$remote_name;
				$tmp_fp = fopen($file, "r");
				$old_options = array_merge(array("CURLOPT_UPLOAD" => false, "CURLOPT_INFILE" => fopen("php://stdin", "r"), "CURLOPT_INFILESIZE" => 0), $this->options);
				$tmp_options = array("CURLOPT_CONNECTTIMEOUT" => 30, "CURLOPT_TIMEOUT" => 600, "CURLOPT_UPLOAD" => true, "CURLOPT_INFILE" => $tmp_fp, "CURLOPT_INFILESIZE" => filesize($file));
				$this->set_opt($tmp_options);
				$this->exec($url, "PUT");
				$http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
				fclose($tmp_fp);
				$this->set_opt($old_options);
				if ($http_code >= 400) { trigger_error("server response {$http_code} when trying to upload '".$this->hide_credential($url)."'", E_USER_WARNING); }
				else {
					$this->data = "";
					return $this->hide_credential($url);
				}
			}
		}
		$this->data = "";
		return false;
	}



	# return all cookies even when no cookie handling is enabled
	#		@param void
	#		@return array						list of all cookies and their attributes
	public function get_cookie() {
		foreach ($this->cookies as $key => $this_cookie) {
			if ($this_cookie["expires"] < time()) { unset($this->cookies[$key]); }
		}
		return $this->cookies;
	}



	# enable / disable session cookie handing
	#		@param boolean	$enable				session cookie handling is default disabled, or set true to enable it if file cookie is not activated
	#		@return void
	public function session_cookie($enable) {
		if (!is_bool($enable)) { trigger_error("session_cookie() expects parameter 1 to be boolean", E_USER_WARNING); }
		elseif (!empty($this->options["CURLOPT_COOKIEFILE"]) || !empty($this->options["CURLOPT_COOKIEJAR"])) {
			trigger_error("session cookie handling is not available when file cookie is activated", E_USER_WARNING);
		}
		else {
			if (!$this->session_cookie && $enable) {									# set directly without updating $this->options so that it can be disabled by resetting curl session
				curl_setopt($this->ch, CURLOPT_COOKIEFILE, "");
				$this->session_cookie = true;
			}
			elseif ($this->session_cookie && !$enable) {								# basically there is no way to disable session cookie in curl library once it is enabled, workaround by resetting curl session
				if (is_resource($this->ch)) { curl_close($this->ch); }
				$this->ch = curl_init();
				$this->set_opt($this->options);
				$this->session_cookie = false;
			}
		}
	}



	# setting various of curl options
	#		@param array	$custom_options		list of name / value pairs used as curl options except curlopt_safe_upload, curlopt_protocols, curlopt_returntransfer, curlopt_headerfunction and curlinfo_header_out
	#		@return void
	public function set_opt($custom_options = array()) {
		$debug_backtrace = debug_backtrace();
		$this_file = __FILE__;
		if (!empty($debug_backtrace[0]["file"]) && !empty($this_file) && ($debug_backtrace[0]["file"] == $this_file)) { $internal = true; } else { $internal = false; }
		foreach ($custom_options as $key => $value) {
			if (!is_string($key)) { trigger_error("curl option name must be string instead of default php constant", E_USER_WARNING); }
			elseif (!defined($key)) {
				$curl_version = curl_version();
				trigger_error("'{$key}' is not a valid option in php v".phpversion()." or curl library v".$curl_version["version"], E_USER_WARNING);
			}
			elseif ($key == "CURLOPT_COOKIEFILE") { $this->set_cookiefile($value); }
			elseif ($key == "CURLOPT_COOKIEJAR") { $this->set_cookiejar($value); }
			elseif (!$internal && in_array($key, array("CURLOPT_SAFE_UPLOAD", "CURLOPT_PROTOCOLS", "CURLOPT_RETURNTRANSFER", "CURLOPT_HEADERFUNCTION", "CURLINFO_HEADER_OUT"))) {
				trigger_error("'{$key}' option is locked in this class to ensure functionalities", E_USER_WARNING);
			}
			else {
				if (curl_setopt($this->ch, constant($key), $value)) { $this->options[$key] = $value; }
			}
		}
	}



	# return response from last curl execution
	#		@param void
	#		@return string						either http response or error message, except for download / upload request
	public function get_data() {
		return $this->data;
	}



	# return variables extracted from response headers
	#		@param void
	#		@return array						list of variables found in response headers, such as content-type, last-modified, etc
	public function get_var() {
		return $this->header_vars;
	}



	# return detail log
	#		@param void
	#		@return array						list of varies information related to last request
	public function get_log() {
		return $this->log;
	}



	# close curl session and clear all information related to last request
	#		@param void
	#		@param void
	public function close() {
		if (is_resource($this->ch)) { curl_close($this->ch); }
		$this->data = "";
		$this->header_vars = $this->cookies = array();
		$this->log = array("host" => null, "summary" => null, "request" => array(), "response" => array(), "set-cookie" => array(), "curlinfo" => array());
	}



	# initialize all variables to default values
	#		@param void
	#		@return void
	private function init() {
		$this->data = "";
		$this->header_vars = $this->cookies = array();
		$this->log = array("host" => null, "summary" => null, "request" => array(), "response" => array(), "set-cookie" => array(), "curlinfo" => array());
		$this->options = array(
			"CURLOPT_SSL_VERIFYPEER" => true,											# (ssl) enable ca root certificate checking
			"CURLOPT_SSL_VERIFYHOST" => 2,												# (ssl) enable hostname checking in ssl certification
			"CURLOPT_SSLVERSION" => 0,													# (ssl) default behaviour in curl library is detecting most secure version supported at both ends
			"CURLOPT_CAINFO" => dirname(getcwd()).DIRECTORY_SEPARATOR."cacert.pem",		# (ssl) updated ca root certificate (revision 2017-01-18 downloaded from https://curl.haxx.se/docs/caextract.html)
			"CURLOPT_SAFE_UPLOAD" => true,												# (method, locked) better approach for file upload operation since php v5.5.0
			"CURLOPT_PROTOCOLS" => CURLPROTO_HTTP | CURLPROTO_HTTPS | CURLPROTO_FTP,	# (request, locked) allow specified protocols only
			"CURLOPT_ENCODING" => "",													# (response) enable all supported compression
			"CURLOPT_FAILONERROR" => false,												# (response) always returns response even if response is http 4xx-5xx error
			"CURLOPT_RETURNTRANSFER" => true,											# (response, locked) always returns response as string instead of direct output
			"CURLOPT_FOLLOWLOCATION" => true,											# (redirect) enable http 3xx redirection
			"CURLOPT_MAXREDIRS" => 5,													# (redirect) maximum number of http 3xx redirection
			"CURLOPT_AUTOREFERER" => true,												# (redirect) automatically set referer in case of redirection
			"CURLOPT_CONNECTTIMEOUT" => 15,												# (timeout) connection timeout
			"CURLOPT_TIMEOUT" => 45,													# (timeout) request timeout
			"CURLOPT_REFERER" => "",													# (header) always begins with direct access
			"CURLOPT_USERAGENT" => "LazyCurl/1.0",										# (header) default user agent
			"CURLOPT_HEADERFUNCTION" => array($this, "header_handler"),					# (header, locked) callback function to extract incoming header
			"CURLINFO_HEADER_OUT" => true,												# (header, locked) dump outgoing header to curlinfo
		);
		# disable ca root certificate checking in curl library before v7.24.0 (bundled openssl version is too old to check or load latest certificate)
		$curl_version = curl_version();
		if (version_compare($curl_version["version"], "7.24.0", "<")) {
			$this->options["CURLOPT_SSL_VERIFYPEER"] = false;
			unset($this->options["CURLOPT_CAINFO"]);
		}
		# ignore safe upload if not supported, this option is added in php 5.5 and removed in php 7.0
		if (version_compare(phpversion(), "5.5.0", "<") || version_compare(phpversion(), "7.0.0", ">=")) { unset($this->options["CURLOPT_SAFE_UPLOAD"]); }
		# Initialize Curl Session
		$this->ch = curl_init();
		$this->set_opt($this->options);
	}



	# get current date / time string in a specific timezone
	#		@param string	$timezone			specify a supported timezone
	#		@return string						date / time string a format "Y-m-d H:i:s O"
	private function now($timezone = "utc") {
		if (!in_array(strtolower($timezone), array_map("strtolower", timezone_identifiers_list()))) { $timezone = "utc"; }
		$now = new DateTime(null, new DateTimeZone($timezone));
		return $now->format("Y-m-d H:i:s O");
	}



	# check if a file can be created or updated
	#		@param string	$file				absolute local path of a file or directory
	#		@return boolean						true if file can be created or updated, false otherwise
	# note:
	#		1. php built-in function "is_writable" always return false if file not exists, and therefore cannot be used to check if a file can be created or not
	#		2. this function checks if a new file can be created or existing file can be updated
	#		3. it also check if a new file can be created within a directory which is passed as parameter
	private function is_writable($file) {
		if (file_exists($file) || is_dir($file)) { return is_writable($file); } else { return is_writable(dirname($file)); }
	}



	# extract valid ipv4 addresses from string
	#		@param string	$str				any input string
	#		@return array						list of extracted ip
	private function extract_ips($str) {
		$validated_ips = array();
		preg_match_all("/\D(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(?=\D)/ims", " {$str} ", $matches);
		foreach ($matches[1] as $value) {
			if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { $validated_ips[] = $value; }
		}
		return $validated_ips;
	}



	# mask the credential with asterisk to prevent showing in plain text
	#		@param string	$str				any input string
	#		@return string						output string with masked credential in http / ftp url or ftp command
	private function hide_credential($str) {
		if (preg_match("/^(https?|ftp):\/\//ims", $str)) {								# http / ftp url
			if ($parts = parse_url($str)) {												# excluding malformed url
				extract($parts, EXTR_PREFIX_ALL, "url");
				$str =  (isset($url_scheme)) ? "{$url_scheme}://" : "";
				$str .= (isset($url_user) || isset($url_pass)) ? "*:*@" : "";			# use "*" to indicate that authentication is required
				$str .= (isset($url_host)) ? $url_host : "";
				$str .= (isset($url_port)) ? ":{$url_port}" : "";
				$str .= (isset($url_path)) ? $url_path : "";
				$str .= (isset($url_query)) ? "?{$url_query}" : "";
				$str .= (isset($url_fragment)) ? "#{$url_fragment}" : "";
			}
		}
		else { $str = preg_replace("/^(USER|PASS)\s+\S+$/msU", "$1 *", $str); }			# ftp login command
		return $str;
	}



	# inspect set cookie header and update cookie variable
	#		@param string	$set_cookie_header	cookie related http response header
	#		@return void
	private function cookie_handler($set_cookie_header) {
		if (!empty($set_cookie_header)) {
			$set_cookie = array("name" => null, "value" => null, "expires" => null, "max-age" => null, "path" => null, "domain" => null, "hostonly" => true, "secure" => false, "httponly" => false, "sessiononly" => false);
			# extract cookie and its attributes
			$set_cookie_parts = explode(";", $set_cookie_header);
			for ($i=0; $i<count($set_cookie_parts); $i++) {
				$set_cookie_pair = explode("=", trim($set_cookie_parts[$i]), 2);
				$key = (isset($set_cookie_pair[0])) ? trim($set_cookie_pair[0]) : null;
				$value = (isset($set_cookie_pair[1])) ? trim($set_cookie_pair[1]) : null;
				if (!is_null($key) && !is_null($value) && ($i == 0)) {																									# cookie name and value
					$set_cookie["name"] = $key;
					$set_cookie["value"] = $value;
				}
				elseif (!is_null($key) && !is_null($value) && array_key_exists(strtolower($key), $set_cookie)) { $set_cookie[strtolower($key)] = $value; }				# name / value pair
				elseif (!is_null($key) && isset($set_cookie[strtolower($key)])) { $set_cookie[strtolower($key)] = true; }												# true / false
			}
			# get the best value as expiry timestamp as it is allowed to have none, either expires or max-age, or both
			if (!empty($set_cookie["expires"])) { $set_cookie["expires"] = strtotime($set_cookie["expires"]); }															# default, use expires
			elseif (!empty($set_cookie["max-age"])) { $set_cookie["expires"] = time() + intval($set_cookie["max-age"]); }												# otherwise, use max-age
			else {																																						# otherwise, use 15 minutes for a session
				$set_cookie["expires"] = time() + 900;
				$set_cookie["sessiononly"] = true;
			}
			unset($set_cookie["max-age"]);
			# modify domain and hostonly attributes for better association
			$set_cookie["domain"] = trim($set_cookie["domain"], ".");
			if (!empty($set_cookie["domain"])) { $set_cookie["hostonly"] = false; }												# domain is provided in set-cookie indicating sub-domain is allowed
			else { $set_cookie["domain"] = parse_url(curl_getinfo($this->ch, CURLINFO_EFFECTIVE_URL), PHP_URL_HOST); }			# extract domain from last effective url
			# modify path attribute for better association
			$set_cookie["path"] = trim($set_cookie["path"], "/");
			$set_cookie["path"] = (!empty($set_cookie["path"])) ? "/".$set_cookie["path"]."/" : "/";
			# create an array key using secure, domain, path and name to simplify add and update operations
			$key = ($set_cookie["secure"] ? "https" : "http")."://".$set_cookie["domain"].$set_cookie["path"].$set_cookie["name"];
			if (array_key_exists($key, $this->cookies)) {
				$this->cookies[$key]["value"] = $set_cookie["value"];
				$this->cookies[$key]["expires"] = $set_cookie["expires"];
			}
			else { $this->cookies[$key] = $set_cookie; }
			# copy cookie to log as well
			if ($set_cookie["expires"] >= time()) { $this->log["set-cookie"][$this->location_idx][$set_cookie["name"]] = $set_cookie["value"]; }
		}
	}



	# callback function for http header handling
	#		@param resource	$ch					curl resource
	#		@param string	$header				http response header, normally with a trailing line break
	private function header_handler($ch, $header) {
		$line = trim($header);
		if (!empty($line)) {
			if (count($this->log["response"]) == 0) { $this->location_idx++; }								# first location
			if (!isset($this->log["response"][$this->location_idx])) {										# initialize array for each location
				$this->log["response"][$this->location_idx] = $this->log["set-cookie"][$this->location_idx] = $this->header_vars[$this->location_idx] = array();
			}
			$this->log["response"][$this->location_idx][] = $line;
			# inspect name / value pair
			$pair = explode(":", $header, 2);
			if (count($pair) == 2) {
				$pair[0] = strtolower(trim($pair[0]));
				$pair[1] = trim($pair[1]);
				if ($pair[0] == "set-cookie") { $this->cookie_handler($pair[1]); }							# received cookie headers will still be processed as read-only variables even when no cookie handling is enabled
				else {
					# store name / value pair, and additionally an array of valid ip extracted from value that may be useful sometimes
					$this->header_vars[$this->location_idx][$pair[0]] = $pair[1];
					$extracted_ips = $this->extract_ips($pair[1]);
					if (!empty($extracted_ips)) { $this->header_vars[$this->location_idx][$pair[0].":ip"] = $extracted_ips; }
				}
			}
		}
		else { $this->location_idx++; }																		# next header is related to a redirected location
		return strlen($header);
	}



	# load cookie from local file and disable session cookie handling
	#		@param string	$file				absolute path of a local file with read permission
	#		@return void
	private function set_cookiefile($file) {
		if (!empty($this->options["CURLOPT_COOKIEFILE"])) { trigger_error("file cookie is activated and cannot be modified", E_USER_WARNING); }
		elseif (empty($file)) { trigger_error("an accessible file is required for CURLOPT_COOKIEFILE", E_USER_WARNING); }
		else {
			if (!file_exists($file)) { touch($file); }
			if (is_dir($file)) { trigger_error("'{$file}' is a directory", E_USER_WARNING); }
			elseif (!is_readable($file)) { trigger_error("no read permission to access '{$file}'", E_USER_WARNING); }
			else {
				$this->session_cookie = false;
				if (substr($file, 0, 2) == ".".DIRECTORY_SEPARATOR) { $file = rtrim(getcwd(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.substr($file, 2); }				# fix relative path
				$this->options["CURLOPT_COOKIEFILE"] = $file;
				curl_setopt($this->ch, CURLOPT_COOKIEFILE, $file);
			}
		}
	}



	# save cookie to local file and disable session cookie handling
	#		@param string	$file				absolute path of a local file with write permission
	#		@return void
	private function set_cookiejar($file) {
		if (!empty($this->options["CURLOPT_COOKIEJAR"])) { trigger_error("file cookie is activated and cannot be modified", E_USER_WARNING); }
		elseif (empty($file)) { trigger_error("an accessible file is required for CURLOPT_COOKIEJAR", E_USER_WARNING); }
		elseif (is_dir($file)) { trigger_error("'{$file}' is a directory", E_USER_WARNING); }
		elseif (!$this->is_writable($file)) { trigger_error("no write permission to access '{$file}'", E_USER_WARNING); }
		else {
			$this->session_cookie = false;
			if (substr($file, 0, 2) == ".".DIRECTORY_SEPARATOR) { $file = rtrim(getcwd(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.substr($file, 2); }					# fix relative path
			$this->options["CURLOPT_COOKIEJAR"] = $file;
			curl_setopt($this->ch, CURLOPT_COOKIEJAR, $file);
		}
	}



	# recursively convert multi-dimensional (nested) array to single-dimensional and maintain index association
	#		@param array	$input_value		any array without object
	#		@param string	$parent_key			array key of parent array
	private function array_flatten_recursive($input_value, $parent_key = "") {
		$output_value = array();
		foreach ($input_value as $key => $value) {
			$this_key = (empty($parent_key)) ? $key : $parent_key."[{$key}]";
			if (is_array($value)) {
				$this_value = $this->array_flatten_recursive($value, $this_key);
				if (!empty($this_value)) { $output_value = array_merge($output_value, $this_value); } else { $output_value[$this_key] = null; }
			}
			else { $output_value[$this_key] = $value; }
		}
		return $output_value;
	}



	# callback function to modify field data in order to use a better approach for file upload operation
	#		@param string	$value				an array value passed by reference
	#		@param string	$key				un-necessary array key passed as parameter in callback function
	#		@param array	$manifest			list of files to be uploaded except http get request
	private function field_validation(&$value, $key, $manifest) {
		if (substr($value, 0, 1) == "@") {
			if (in_array($value, $manifest)) {
				# extract file attributes from the string value
				$values = explode("\t", substr($value, 1), 3);
				$local_file = $values[0]; $post_name = $mime_type = null;
				for ($i=1; $i<count($values); $i++) {
					if (stripos($values[$i], "filename=") === 0) { $post_name = substr($values[$i], 9); }
					elseif (stripos($values[$i], "type=") === 0) { $mime_type = substr($values[$i], 5); }
				}
				# download remote file if necessary
				if (preg_match("/^(https?|ftp):\/\//ims", $local_file)) { $this->get_file($local_file, $post_name, $mime_type); }
				# apply changes
				if (substr($local_file, 0, 2) == ".".DIRECTORY_SEPARATOR) { $local_file = rtrim(getcwd(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.substr($local_file, 2); }				# fix relative path
				if (file_exists($local_file)) {
					if (empty($post_name)) { $post_name = basename($local_file); }
					if (empty($mime_type) && function_exists("mime_content_type")) { $mime_type = mime_content_type($local_file); }
					if (version_compare(phpversion(), "5.5.0", ">=")) { $value = new CURLFile($local_file, $mime_type, $post_name); }
					else { $value = "@{$local_file};type={$mime_type};filename={$post_name}"; }
				}
				else { $value = ""; }													# clear value if file is missing to prevent sending absolute local path as string
			}
			elseif (version_compare(phpversion(), "5.5.0", "<")) { $value = " {$value}"; }
		}
	}



	# create a temporary curl session to download remote file
	#		@param string	$remote_file		target url passed by reference and will be updated with local path of downloaded file
	#		@param string	$post_name			post name passed by reference and will be used as file name in target server
	#		@param string	$mime_type			mime type passed by reference and will be used as file type in target server
	#		@return void
	private function get_file(&$remote_file, &$post_name, &$mime_type) {
		$tmp_name = $tmp_type = null;
		$tmp_file = tempnam("temp", "lc_");
		$tmp_fp = fopen($tmp_file, "w");
		$mem_fp = fopen("php://temp", "w+");
		$this->tmp_files[] = $tmp_file;
		$tmp_options = array(
			"CURLOPT_SSL_VERIFYPEER" => true,											# (ssl) enable ca root certificate checking
			"CURLOPT_SSL_VERIFYHOST" => 2,												# (ssl) enable hostname checking in ssl certification
			"CURLOPT_SSLVERSION" => 0,													# (ssl) default behaviour in curl library is detecting most secure version supported at both ends
			"CURLOPT_CAINFO" => dirname(getcwd()).DIRECTORY_SEPARATOR."cacert.pem",		# (ssl) updated ca root certificate (revision 2017-01-18 downloaded from https://curl.haxx.se/docs/caextract.html)
			"CURLOPT_PROTOCOLS" => CURLPROTO_HTTP | CURLPROTO_HTTPS | CURLPROTO_FTP,	# (request) allow specified protocols only
			"CURLOPT_ENCODING" => "",													# (response) enable all supported compression
			"CURLOPT_FAILONERROR" => false,												# (response) always returns response even if response is http 4xx-5xx error
			"CURLOPT_RETURNTRANSFER" => true,											# (response) always returns response as string instead of direct output
			"CURLOPT_FOLLOWLOCATION" => true,											# (redirect) enable http 3xx redirection
			"CURLOPT_MAXREDIRS" => 5,													# (redirect) maximum number of http 3xx redirection
			"CURLOPT_AUTOREFERER" => true,												# (redirect) automatically set referer in case of redirection
			"CURLOPT_CONNECTTIMEOUT" => 30,												# (timeout) connection timeout
			"CURLOPT_TIMEOUT" => 600,													# (timeout) request timeout
			"CURLOPT_FILE" => $tmp_fp,													# (i/o) redirect body output to temp file
			"CURLOPT_WRITEHEADER" => $mem_fp,											# (i/o) redirect header output to memory file
		);
		# disable ca root certificate checking in curl library before v7.24.0 (bundled openssl version is too old to check or load latest certificate)
		$curl_version = curl_version();
		if (version_compare($curl_version["version"], "7.24.0", "<")) {
			$tmp_options["CURLOPT_SSL_VERIFYPEER"] = false;
			unset($tmp_options["CURLOPT_CAINFO"]);
		}
		# begin download
		$tmp_ch = curl_init();
		foreach ($tmp_options as $key => $value) {
			if (defined($key)) { curl_setopt($tmp_ch, constant($key), $value); }
		}
		curl_setopt($tmp_ch, CURLOPT_URL, $remote_file);
		curl_exec($tmp_ch);
		$http_code = curl_getinfo($tmp_ch, CURLINFO_HTTP_CODE);
		$tmp_type = curl_getinfo($tmp_ch, CURLINFO_CONTENT_TYPE);
		curl_setopt($tmp_ch, CURLOPT_FILE, fopen("php://stdout", "w"));
		curl_close($tmp_ch);
		# store headers to variable
		fseek($mem_fp, 0);
		$tmp_headers = stream_get_contents($mem_fp);
		fclose($mem_fp);
		fclose($tmp_fp);
		if ($http_code >= 400) { trigger_error("server response {$http_code} when trying to access '".$this->hide_credential($remote_file)."'", E_USER_WARNING); }
		else {
			# find the best matching file name for downloaded file
			if (empty($post_name)) {
				preg_match_all("/^content-disposition:\s*[^;]+;.*filename\s*=\s*([^;]+)[;\s]/imsU", $tmp_headers, $matches);
				if (!empty($matches[1])) { $tmp_name = trim($matches[1][count($matches[1]) - 1], " '\""); }						# (name + ext) first priority in http header
				if (empty($tmp_name)) {
					$pathinfo = pathinfo(parse_url($remote_file, PHP_URL_PATH));
					if (!empty($pathinfo["filename"])) { $tmp_name = $pathinfo["filename"]; }									# (name) failover to file name in target url
					else { $tmp_name = pathinfo($tmp_file, PATHINFO_FILENAME); }												# (name) lastly use temp file name
					if (!empty($pathinfo["extension"])) { $tmp_name .= ".".$pathinfo["extension"]; }							# (ext) find file extension in target url
					elseif (!empty($mime_type)) { $tmp_name .= ".".str_replace("/", ".", $mime_type); }							# (ext) failover to specified mime type as extension
					elseif (!empty($tmp_type)) { $tmp_name .= ".".str_replace("/", ".", $tmp_type); }							# (ext) failover to content type in http header as descriptive extension
				}
			}
			# update parameters that are passed by reference only if file has content
			if (filesize($tmp_file) > 0) {
				$remote_file = $tmp_file;
				if (empty($post_name) && !empty($tmp_name)) { $post_name = $tmp_name; }
				if (empty($mime_type) && !empty($tmp_type)) { $mime_type = $tmp_type; }
			}
		}
	}
}

?>