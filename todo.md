Todo list:

* streamWrite class - UnitTests
* streamRead class - tests










```php

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



### streamRead

streamRead is like `WriteToFile`


```php
// Write to file GZ
$streamRead=new ClickHouseDB\Transport\StreamRead($stream);
$streamRead->applyGzip();   // Add Gzip zlib.deflate in stream
$r=$client->streamRead($streamRead,'SELECT sin(number) as sin,cos(number) as cos FROM {table_name} LIMIT 30 FORMAT JSONEachRow', ['table_name'=>'system.number']);

// Send to closure
$streamRead=new ClickHouseDB\Transport\StreamRead($stream);
$streamRead->closure($callable);
$r=$client->streamRead($streamRead,'SELECT sin(number) as sin,cos(number) as cos FROM {table_name} LIMIT 30 FORMAT JSONEachRow', ['table_name'=>'system.number']);


```