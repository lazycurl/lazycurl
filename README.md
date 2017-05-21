## LazyCurl
A PHP class which brings back @-prefix usage in CURL requests to live, even in PHP 7. <http://lazycurl.net/>

## About
The [PHP CURL library](http://php.net/manual/book.curl.php) is an extremely powerful tool supporting a wide range of protocols. Yet it may be too powerful that there are [over 140 customizable options](http://php.net/manual/function.curl-setopt.php) and some of them are affecting one another. LazyCurl is developed to provide an easy-to-use interface for most HTTP requests.

The @-prefix usage in PHP CURL was once a handy method for uploading file in HTTP POST requests. It was deprecated as of PHP 5.5 for various reasons. LazyCurl provides a secure approach in using @-prefix and extends its capability from local file to HTTP URL.

## Features
* Simplify HTTP CURL requests.
* Default using the best SSL CURL options with [updated CA certificates](https://curl.haxx.se/docs/caextract.html).
* Logs HTTP requests being sent and captures responses including redirections.
* Extracts all cookies from HTTP responses.
* Supports multi-dimensional array in HTTP POST requests.
* Provides @-prefix usage in PHP 5 and PHP 7 with @URL capability.
* Streaming transfers large file without memory concern.
* Supports FTP stream upload single file.
* Only 11 methods to achieve everything above.

## System Requirement
* PHP 5.3+ (recommended 5.5+ for better @-prefix handling)
* CURL Library 7.19.4+ (recommended 7.34+ for better security with TLS 1.1 / 1.2)
* [PHP Fileinfo extension](http://php.net/manual/book.fileinfo.php) (optional for mime type detection)
* Read / Write permission is required for persistent cookies and temporary downloaded files

## Tested Environment
* PHP 5.3.0 with CURL Library 7.19.4
* PHP 5.4.0 with CURL Library 7.24.0
* PHP 5.5.0 with CURL Library 7.30.0
* PHP 5.6.0 with CURL Library 7.36.0
* PHP 7.0.0 with CURL Library 7.43.0
* PHP 7.1.0 with CURL Library 7.51.0

## Examples
LazyCurl comes with 6 examples to show you how to use all 11 methods in different scenarios. Simply upload to web server and open in browser to see them in live.
* http_get_basic.php - *get response, log and cookie from a simple HTTP GET request*
* http_get_advanced.php - *enable cookie, setting CURL options and reset to default*
* http_post_basic.php - *send HTTP POST request with an associative multi-dimensional array*
* http_post_advanced.php - *uploading local and remote file using @-prefix*
* file_transfer.php - *stream download HTTP file and stream upload to FTP server*
* file_cookie.php - *save persistent cookie to local file*

## Notes
Additional notes are embedded in the PHP class. It is strongly recommended you read them carefully to fully understand its capabilities and limitations.

## License
The contents of this repository is released under the [MIT license](http://opensource.org/licenses/MIT).
