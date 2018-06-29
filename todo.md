```php

$source = fopen($file_name, 'rb');

// @todo:1
$client->streamInsert($source,$callable,'summing_url_views',$cols=[]);
// @todo:2
$client->streamRead($source,$callable,'SELECT * FROM summing_url_views',$bind=[]);

// @todo:3

$client->query('SELECT sin(39) as sinx',$bind)->row('sinx');
$client->query('DROP TABLE OFOR',$bind)->isOk();


```