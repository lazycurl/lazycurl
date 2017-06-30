## How To Use @-prefix With LazyCurl
For example, if you want to upload some files with form data:

```php
<?php
$fields = array(
	'username' => 'John Smith',
	'email' => 'john.smith@example.com',
	'photo' => '@/local/path/photo.jpg',
	'application' => '@http://example.com/user/john.smith/signed.pdf',
	'twitter' => '@john.smith.does.not.exist'
);
$files = array(
	'@/local/path/photo.jpg',
	'@http://example.com/user/john.smith/signed.pdf'
);

$curl = new LazyCurl();
$curl->exec('https://example.com/path/to/target.php', 'POST', $fields, $files);
$curl->close();
?>
```

That's it. The target PHP will receive text fields 'username', 'email' and 'twitter', together with 2 uploaded files.
