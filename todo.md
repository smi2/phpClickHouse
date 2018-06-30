```php

$stream = fopen('php://memory','r+');
fwrite($stream, '{"a":8}'.PHP_EOL.'{"a":9}'.PHP_EOL );
rewind($stream);

// @todo:1
$client->streamWrite($source,$callable,'INSERT INTO summing_url_views',$bind=[]);
// @todo:2
$client->streamRead($source,$callable,'SELECT * FROM summing_url_views',$bind=[]);

// @todo:3

$client->query('SELECT sin(39) as sinx',$bind)->row('sinx');
$client->query('WITH 1+1 AS a SELECT a*a as ss');
$client->query("\t".' INSERT SELECT');
$client->query('DROP TABLE OFOR',$bind)->isOk();



```