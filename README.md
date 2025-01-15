PHP ClickHouse wrapper
======================

[![Downloads](https://poser.pugx.org/smi2/phpClickHouse/d/total.svg)](https://packagist.org/packages/smi2/phpClickHouse)
[![Packagist](https://poser.pugx.org/smi2/phpClickHouse/v/stable.svg)](https://packagist.org/packages/smi2/phpClickHouse)
[![Licence](https://poser.pugx.org/smi2/phpClickHouse/license.svg)](https://packagist.org/packages/smi2/phpClickHouse)

## Features

- No dependency, only Curl (support php `>=7.1` )
- Select parallel queries (asynchronous)
- Asynchronous bulk inserts from CSV file
- Http compression (Gzip), for bulk inserts
- Find active host, check cluster
- Select WHERE IN ( _local csv file_ )
- SQL conditions & template
- tablesSize & databaseSize
- listPartitions
- truncateTable in cluster
- Insert array as column
- Get master node replica in cluster
- Get tableSize in all nodes
- Async get ClickHouse progress function
- streamRead/Write & Closure functions

[Russian articles habr.com 1](https://habrahabr.ru/company/smi2/blog/317682/) [on habr.com 2](https://habr.com/company/smi2/blog/314558/)

## Install composer

```
composer require smi2/phpclickhouse
```


In php
```php

// vendor autoload 
$db = new ClickHouseDB\Client(['config_array']);

if (!$db->ping()) echo 'Error connect';
```

Last stable version for 
* php 5.6 <= `1.1.2`
* php 7.2 <= `1.3.10`
* php 7.3 >= `1.4.x ... 1.5.X` 
* php 8.4 >= `1.6.0`


[Packagist](https://packagist.org/packages/smi2/phpclickhouse)

## Start

Connect and select database:
```php
$config = [
    'host' => '192.168.1.1',
    'port' => '8123',
    'username' => 'default',
    'password' => '',
    'https' => true
];
$db = new ClickHouseDB\Client($config);
$db->database('default');
$db->setTimeout(1.5);      // 1 second , support only Int value
$db->setTimeout(10);       // 10 seconds
$db->setConnectTimeOut(5); // 5 seconds
$db->ping(true); // if can`t connect throw exception  
```

Show tables:
```php
print_r($db->showTables());
```

Create table:
```php
$db->write('
    CREATE TABLE IF NOT EXISTS summing_url_views (
        event_date Date DEFAULT toDate(event_time),
        event_time DateTime,
        site_id Int32,
        site_key String,
        views Int32,
        v_00 Int32,
        v_55 Int32
    )
    ENGINE = SummingMergeTree(event_date, (site_id, site_key, event_time, event_date), 8192)
');
```
Show create table:
```php
echo $db->showCreateTable('summing_url_views');
```
Insert data:
```php
$stat = $db->insert('summing_url_views',
    [
        [time(), 'HASH1', 2345, 22, 20, 2],
        [time(), 'HASH2', 2345, 12, 9,  3],
        [time(), 'HASH3', 5345, 33, 33, 0],
        [time(), 'HASH3', 5345, 55, 0, 55],
    ],
    ['event_time', 'site_key', 'site_id', 'views', 'v_00', 'v_55']
);
```

If you need to insert UInt64 value, you can wrap the value in `ClickHouseDB\Type\UInt64` DTO.

```php
$statement = $db->insert('table_name',
    [
        [time(), UInt64::fromString('18446744073709551615')],
    ],
    ['event_time', 'uint64_type_column']
);
UInt64::fromString('18446744073709551615')
```

Select:
```php
$statement = $db->select('SELECT * FROM summing_url_views LIMIT 2');
```

Work with Statement:
```php
// Count select rows
$statement->count();

// Count all rows
$statement->countAll();

// fetch one row
$statement->fetchOne();

// get extremes min
print_r($statement->extremesMin());

// totals row
print_r($statement->totals());

// result all
print_r($statement->rows());

// totalTimeRequest
print_r($statement->totalTimeRequest());

// raw answer JsonDecode array, for economy memory
print_r($statement->rawData());

// raw curl_info answer
print_r($statement->responseInfo());

// human size info
print_r($statement->info());

// if clickhouse-server version >= 54011
$db->settings()->set('output_format_write_statistics',true);
print_r($statement->statistics());


// Statement Iterator
$state=$this->client->select('SELECT (number+1) as nnums FROM system.numbers LIMIT 5');
foreach ($state as $key=>$value) {
    echo $value['nnums'];
}

```

Select result as tree:
```php
$statement = $db->select('
    SELECT event_date, site_key, sum(views), avg(views)
    FROM summing_url_views
    WHERE site_id < 3333
    GROUP BY event_date, url_hash
    WITH TOTALS
');

print_r($statement->rowsAsTree('event_date.site_key'));

/*
(
    [2016-07-18] => Array
        (
            [HASH2] => Array
                (
                    [event_date] => 2016-07-18
                    [url_hash] => HASH2
                    [sum(views)] => 12
                    [avg(views)] => 12
                )
            [HASH1] => Array
                (
                    [event_date] => 2016-07-18
                    [url_hash] => HASH1
                    [sum(views)] => 22
                    [avg(views)] => 22
                )
        )
)
*/
```

Drop table:

```php
$db->write('DROP TABLE IF EXISTS summing_url_views');
```



Features
--------
### Select parallel queries (asynchronous)
```php
$state1 = $db->selectAsync('SELECT 1 as ping');
$state2 = $db->selectAsync('SELECT 2 as ping');

// run
$db->executeAsync();

// result
print_r($state1->rows());
print_r($state2->fetchOne('ping'));
```

### Parallelizing massive inserts from CSV file
```php
$file_data_names = [
    '/tmp/clickHouseDB_test.1.data',
    '/tmp/clickHouseDB_test.2.data',
    '/tmp/clickHouseDB_test.3.data',
    '/tmp/clickHouseDB_test.4.data',
    '/tmp/clickHouseDB_test.5.data',
];

// insert all files
$stat = $db->insertBatchFiles(
    'summing_url_views',
    $file_data_names,
    ['event_time', 'site_key', 'site_id', 'views', 'v_00', 'v_55']
);
```
### Parallelizing errors

selectAsync without executeAsync

```php
$select = $db->selectAsync('SELECT * FROM summing_url_views LIMIT 1');
$insert = $db->insertBatchFiles('summing_url_views', ['/tmp/clickHouseDB_test.1.data'], ['event_time']);
// 'Exception' with message 'Queue must be empty, before insertBatch, need executeAsync'
```
see example/exam5_error_async.php

### Gzip & enable_http_compression

On fly read CSV file and compress zlib.deflate.

```php
$db->settings()->max_execution_time(200);
$db->enableHttpCompression(true);

$result_insert = $db->insertBatchFiles('summing_url_views', $file_data_names, [...]);


foreach ($result_insert as $fileName => $state) {
    echo $fileName . ' => ' . json_encode($state->info_upload()) . PHP_EOL;
}
```

see speed test `example/exam08_http_gzip_batch_insert.php`

### Max execution time

```php
$db->settings()->max_execution_time(200); // second
```




### Connection without port 

```php 
$config['host']='blabla.com';
$config['port']=0;
// getUri() === 'http://blabla.com'


$config['host']='blabla.com/urls';
$config['port']=8765;
// getUri() === 'http://blabla.com/urls'

$config['host']='blabla.com:2224';
$config['port']=1234;
// getUri() === 'http://blabla.com:2224'





``` 


### tablesSize & databaseSize

Result in _human size_

```php
print_r($db->databaseSize());
print_r($db->tablesSize());
print_r($db->tableSize('summing_partions_views'));
```

### Partitions

```php
$count_result = 2;
print_r($db->partitions('summing_partions_views', $count_result));
```

### Select WHERE IN ( _local csv file_ )

```php
$file_name_data1 = '/tmp/temp_csv.txt'; // two column file [int,string]
$whereIn = new \ClickHouseDB\Query\WhereInFile();
$whereIn->attachFile($file_name_data1, 'namex', ['site_id' => 'Int32', 'site_hash' => 'String'], \ClickHouseDB\Query\WhereInFile::FORMAT_CSV);
$result = $db->select($sql, [], $whereIn);

// see example/exam7_where_in.php
```


### Bindings

Bindings:

```php
$date1 = new DateTime("now"); // DateTimeInterface

$Bindings = [
  'select_date' => ['2000-10-10', '2000-10-11', '2000-10-12'],
  'datetime'=>$date,
  'limit' => 5,
  'from_table' => 'table'
];

$statement = $db->selectAsync("SELECT FROM {from_table} WHERE datetime=:datetime limit {limit}", $Bindings);

// Double bind in {KEY}
$keys=[
            'A'=>'{B}',
            'B'=>':C',
            'C'=>123,
            'Z'=>[':C',':B',':C']
        ];
$this->client->selectAsync('{A} :Z', $keys)->sql() // ==   "123 ':C',':B',':C' FORMAT JSON",


```


#### Simple sql conditions & template

Conditions is deprecated, if need use:
`$db->enableQueryConditions();`

Example with QueryConditions:

```php

$db->enableQueryConditions();

$input_params = [
  'select_date' => ['2000-10-10', '2000-10-11', '2000-10-12'],
  'limit' => 5,
  'from_table' => 'table'
];

$select = '
    SELECT * FROM {from_table}
    WHERE
    {if select_date}
        event_date IN (:select_date)
    {else}
        event_date=today()
    {/if}
    {if limit}
    LIMIT {limit}
    {/if}
';

$statement = $db->selectAsync($select, $input_params);
echo $statement->sql();

/*
SELECT * FROM table
WHERE
event_date IN ('2000-10-10','2000-10-11','2000-10-12')
LIMIT 5
FORMAT JSON
*/

$input_params['select_date'] = false;
$statement = $db->selectAsync($select, $input_params);
echo $statement->sql();

/*
SELECT * FROM table
WHERE
event_date=today()
LIMIT 5
FORMAT JSON
*/

$state1 = $db->selectAsync(
    'SELECT 1 as {key} WHERE {key} = :value',
    ['key' => 'ping', 'value' => 1]
);

// SELECT 1 as ping WHERE ping = "1"
```

Example custom query Degeneration in `exam16_custom_degeneration.php`

```
SELECT {ifint VAR} result_if_intval_NON_ZERO{/if}
SELECT {ifint VAR} result_if_intval_NON_ZERO {else} BLA BLA{/if}
```

### Settings

3 way set any settings
```php
// in array config
$config = [
    'host' => 'x',
    'port' => '8123',
    'username' => 'x',
    'password' => 'x',
    'settings' => ['max_execution_time' => 100]
];
$db = new ClickHouseDB\Client($config);

// settings via constructor
$config = [
    'host' => 'x',
    'port' => '8123',
    'username' => 'x',
    'password' => 'x'
];
$db = new ClickHouseDB\Client($config, ['max_execution_time' => 100]);

// set method
$config = [
    'host' => 'x',
    'port' => '8123',
    'username' => 'x',
    'password' => 'x'
];
$db = new ClickHouseDB\Client($config);
$db->settings()->set('max_execution_time', 100);

// apply array method
$db->settings()->apply([
    'max_execution_time' => 100,
    'max_block_size' => 12345
]);

// check
if ($db->settings()->getSetting('max_execution_time') !== 100) {
    throw new Exception('Bad work settings');
}

// see example/exam10_settings.php
```
### Use session_id with ClickHouse


`useSession()` - make new session_id or use exists `useSession(value)`


```php

// enable session_id
$db->useSession();
$sesion_AA=$db->getSession(); // return session_id

$db->write(' CREATE TEMPORARY TABLE IF NOT EXISTS temp_session_test (number UInt64)');
$db->write(' INSERT INTO temp_session_test SELECT number*1234 FROM system.numbers LIMIT 30');

// reconnect to continue with other session
$db->useSession($sesion_AA);
```

### Array as column

```php
$db->write('
    CREATE TABLE IF NOT EXISTS arrays_test_string (
        s_key String,
        s_arr Array(String)
    )
    ENGINE = Memory
');

$db->insert('arrays_test_string',
    [
        ['HASH1', ["a", "dddd", "xxx"]],
        ['HASH1', ["b'\tx"]],
    ],
    ['s_key', 's_arr']
);

// see example/exam12_array.php
```

Class for FormatLine array

```php
var_dump(
    ClickHouseDB\Quote\FormatLine::CSV(
        ['HASH1', ["a", "dddd", "xxx"]]
    )
);

var_dump(
    ClickHouseDB\Quote\FormatLine::TSV(
        ['HASH1', ["a", "dddd", "xxx"]]
    )
);

// example write to file
$row=['event_time'=>date('Y-m-d H:i:s'),'arr1'=>[1,2,3],'arrs'=>["A","B\nD\nC"]];
file_put_contents($fileName,ClickHouseDB\Quote\FormatLine::TSV($row)."\n",FILE_APPEND);
```

### HTTPS

```php
$db = new ClickHouseDB\Client($config);
$db->settings()->https();
```



### getServer System.Settings & Uptime

```php
print_r($db->getServerUptime());

print_r($db->getServerSystemSettings());

print_r($db->getServerSystemSettings('merge_tree_min_rows_for_concurrent_read'));

```

### ReadOnly ClickHouse user

```php
$config = [
    'host' => '192.168.1.20',
    'port' => '8123',
    'username' => 'ro',
    'password' => 'ro',
    'readonly' => true
];
```


### Direct write to file

Send result from clickhouse, without parse json.

```php
$WriteToFile=new ClickHouseDB\WriteToFile('/tmp/_1_select.csv');
$db->select('select * from summing_url_views',[],null,$WriteToFile);
// or
$db->selectAsync('select * from summing_url_views limit 4',[],null,new ClickHouseDB\WriteToFile('/tmp/_3_select.tab',true,'TabSeparatedWithNames'));
$db->selectAsync('select * from summing_url_views limit 4',[],null,new ClickHouseDB\WriteToFile('/tmp/_4_select.tab',true,'TabSeparated'));
$statement=$db->selectAsync('select * from summing_url_views limit 54',[],null,new ClickHouseDB\WriteToFile('/tmp/_5_select.csv',true,ClickHouseDB\WriteToFile::FORMAT_CSV));
```

## Stream

streamWrite() : Closure stream write

```php

$streamWrite=new ClickHouseDB\Transport\StreamWrite($stream);

$client->streamWrite(
        $streamWrite,                                   // StreamWrite Class
        'INSERT INTO {table_name} FORMAT JSONEachRow',  // SQL Query
        ['table_name'=>'_phpCh_SteamTest']              // Binds
    );
```


### streamWrite & custom Closure & Deflate

```php

$stream = fopen('php://memory','r+');

for($f=0;$f<23;$f++) {  // Make json data in stream
        fwrite($stream, json_encode(['a'=>$f]).PHP_EOL );
}

rewind($stream); // rewind stream


$streamWrite=new ClickHouseDB\Transport\StreamWrite($stream);
$streamWrite->applyGzip();   // Add Gzip zlib.deflate in stream

$callable = function ($ch, $fd, $length) use ($stream) {
    return ($line = fread($stream, $length)) ? $line : '';
};
// Apply closure
$streamWrite->closure($callable);
// Run Query
$r=$client->streamWrite($streamWrite,'INSERT INTO {table_name} FORMAT JSONEachRow', ['table_name'=>'_phpCh_SteamTest']);
// Result
print_r($r->info_upload());

```


### streamRead

streamRead is like `WriteToFile`


```php
$stream = fopen('php://memory','r+');
$streamRead=new ClickHouseDB\Transport\StreamRead($stream);

$r=$client->streamRead($streamRead,'SELECT sin(number) as sin,cos(number) as cos FROM {table_name} LIMIT 4 FORMAT JSONEachRow', ['table_name'=>'system.numbers']);
rewind($stream);
while (($buffer = fgets($stream, 4096)) !== false) {
    echo ">>> ".$buffer;
}
fclose($stream); // Need Close Stream



// Send to closure

$stream = fopen('php://memory','r+');
$streamRead=new ClickHouseDB\Transport\StreamRead($stream);
$callable = function ($ch, $string) use ($stream) {
    // some magic for _BLOCK_ data
    fwrite($stream, str_ireplace('"sin"','"max"',$string));
    return strlen($string);
};

$streamRead->closure($callable);

$r=$client->streamRead($streamRead,'SELECT sin(number) as sin,cos(number) as cos FROM {table_name} LIMIT 44 FORMAT JSONEachRow', ['table_name'=>'system.numbers']);

```


### insert Assoc Bulk

```php
 $oneRow = [
            'one' => 1,
            'two' => 2,
            'thr' => 3,
            ];
            $failRow = [
                'two' => 2,
                'one' => 1,
                'thr' => 3,
            ];

$db->insertAssocBulk([$oneRow, $oneRow, $failRow])
```
### Auth methods

```
   AUTH_METHOD_HEADER       = 1;
   AUTH_METHOD_QUERY_STRING = 2;
   AUTH_METHOD_BASIC_AUTH   = 3;
```

In config set `auth_method`

```php
$config=[
    'host'=>'host.com',
    //...
    'auth_method'=>1,
];

```


### progressFunction

```php
// Apply function

$db->progressFunction(function ($data) {
    echo "CALL FUNCTION:".json_encode($data)."\n";
});
$st=$db->select('SELECT number,sleep(0.2) FROM system.numbers limit 5');


// Print
// ...
// CALL FUNCTION:{"read_rows":"2","read_bytes":"16","total_rows":"0"}
// CALL FUNCTION:{"read_rows":"3","read_bytes":"24","total_rows":"0"}
// ...

```

### ssl CA
```php
$config = [
    'host' => 'cluster.clickhouse.dns.com', // any node name in cluster
    'port' => '8123',
    'sslCA' => '...', 
];
```


### Cluster

```php

$config = [
    'host' => 'cluster.clickhouse.dns.com', // any node name in cluster
    'port' => '8123',
    'username' => 'default', // all node have one login+password
    'password' => ''
];


// client connect first node, by DNS, read list IP, then connect to ALL nodes for check is !OK!


$cl = new ClickHouseDB\Cluster($config);
$cl->setScanTimeOut(2.5); // 2500 ms, max time connect per one node

// Check replica state is OK
if (!$cl->isReplicasIsOk())
{
    throw new Exception('Replica state is bad , error='.$cl->getError());
}

// get array nodes, and clusers
print_r($cl->getNodes());
print_r($cl->getClusterList());


// get node by cluster
$name='some_cluster_name';
print_r($cl->getClusterNodes($name));

// get counts
echo "> Count Shard   = ".$cl->getClusterCountShard($name)."\n";
echo "> Count Replica = ".$cl->getClusterCountReplica($name)."\n";

// get nodes by table & print size per node
$nodes=$cl->getNodesByTable('shara.adpreview_body_views_sharded');
foreach ($nodes as $node)
{
    echo "$node > \n";
    // select one node
    print_r($cl->client($node)->tableSize('adpreview_body_views_sharded'));
    print_r($cl->client($node)->showCreateTable('shara.adpreview_body_views'));
}

// work with one node

// select by IP like "*.248*" = `123.123.123.248`, dilitmer `;`  , if not fount -- select first node
$cli=$cl->clientLike($name,'.298;.964'); // first find .298 then .964 , result is ClickHouseDB\Client

$cli->ping();



// truncate table on cluster
$result=$cl->truncateTable('dbNane.TableName_sharded');

// get one active node ( random )
$cl->activeClient()->setTimeout(500);
$cl->activeClient()->write("DROP TABLE IF EXISTS default.asdasdasd ON CLUSTER cluster2");


// find `is_leader` node
$cl->getMasterNodeForTable('dbNane.TableName_sharded');


// errors
var_dump($cl->getError());


//

```

### Return Extremes

```php
$db->enableExtremes(true);
```

### Enable Log Query

You can log all query in ClickHouse

```php
$db->enableLogQueries();
$db->select('SELECT 1 as p');
print_r($db->select('SELECT * FROM system.query_log')->rows());
```

### isExists

```php
$db->isExists($database,$table);
```


### Debug & Verbose

```php
$db->verbose();
```


Verbose to file|steam:

```php
    // init client
    $cli = new Client($config);
    $cli->verbose();
    // temp stream
    $stream = fopen('php://memory', 'r+');
    // set stream to curl
    $cli->transport()->setStdErrOut($stream);
    // exec curl
    $st=$cli->select('SElect 1 as ppp');
    $st->rows();
    // rewind 
    fseek($stream,0,SEEK_SET);
    
    // output
    echo stream_get_contents($stream);
```


### Dev & PHPUnit Test


* Don't forget to run composer install. It should setup PSR-4 autoloading.
* Then you can simply run vendor/bin/phpunit and it should output the following


```bash
cp phpunit.xml.dist phpunit.xml
mcedit phpunit.xml
```

Edit in phpunit.xml constants:
```xml
<php>
    <env name="CLICKHOUSE_HOST" value="127.0.0.1" />
    <env name="CLICKHOUSE_PORT" value="8123" />
    <env name="CLICKHOUSE_USER" value="default" />
    <env name="CLICKHOUSE_DATABASE" value="phpChTestDefault" />
    <env name="CLICKHOUSE_PASSWORD" value="" />
    <env name="CLICKHOUSE_TMPPATH" value="/tmp" />
</php>
```
Run docker ClickHouse server  
```
cd ./tests
docker-compose up
```

Run test
```bash

./vendor/bin/phpunit

./vendor/bin/phpunit --group ClientTest

./vendor/bin/phpunit --group ClientTest --filter testInsertNestedArray

./vendor/bin/phpunit --group ConditionsTest


```


Run PHPStan

```
# Main
./vendor/bin/phpstan analyse src tests --level 7
# SRC only
./vendor/bin/phpstan analyse src --level 7



# Examples
./vendor/bin/phpstan analyse example -a ./example/Helper.php



```

License
-------

MIT

ChangeLog
---------

See [changeLog.md](CHANGELOG.md)
