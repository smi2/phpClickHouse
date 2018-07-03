PHP ClickHouse wrapper
======================

[![Build Status](https://travis-ci.org/smi2/phpClickHouse.svg)](https://travis-ci.org/smi2/phpClickHouse)
[![Downloads](https://poser.pugx.org/smi2/phpClickHouse/d/total.svg)](https://packagist.org/packages/smi2/phpClickHouse)
[![Packagist](https://poser.pugx.org/smi2/phpClickHouse/v/stable.svg)](https://packagist.org/packages/smi2/phpClickHouse)
[![Licence](https://poser.pugx.org/smi2/phpClickHouse/license.svg)](https://packagist.org/packages/smi2/phpClickHouse)
[![Quality Score](https://scrutinizer-ci.com/g/smi2/phpClickHouse/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/smi2/phpClickHouse)
[![Code Coverage](https://scrutinizer-ci.com/g/smi2/phpClickHouse/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/smi2/phpClickHouse)

## Features

- No dependency, only Curl (support php 5.6, but recommended 7.2 )
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
include_once __DIR__ . '/phpClickHouse/include.php';
$db = new ClickHouseDB\Client(['config_array']);
$db->ping();
```


[Packagist](https://packagist.org/packages/smi2/phpclickhouse)

## Start

Connect and select database:
```php
$config = [
    'host' => '192.168.1.1',
    'port' => '8123',
    'username' => 'default',
    'password' => ''
];
$db = new ClickHouseDB\Client($config);
$db->database('default');
$db->setTimeout(1.5);      // 1500 ms
$db->setTimeout(10);       // 10 seconds
$db->setConnectTimeOut(5); // 5 seconds

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

Drop partitions ( pre production )

```php
$count_old_days = 10;
print_r($db->dropOldPartitions('summing_partions_views', $count_old_days));

// by `partition_id`
print_r($db->dropPartition('summing_partions_views', '201512'));
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

$statement = $db->selectAsync("SELECT FROM {table} WHERE datetime=:datetime limit {limit}", $Bindings);

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
    \ClickHouseDB\FormatLine::CSV(
        ['HASH1', ["a", "dddd", "xxx"]]
    )
);

var_dump(
    \ClickHouseDB\FormatLine::TSV(
        ['HASH1', ["a", "dddd", "xxx"]]
    )
);

// example write to file
$row=['event_time'=>date('Y-m-d H:i:s'),'arr1'=>[1,2,3],'arrs'=>["A","B\nD\nC"]];
file_put_contents($fileName,\ClickHouseDB\FormatLine::TSV($row)."\n",FILE_APPEND);
```

### Cluster drop old Partitions

Example code :

```php
class my
{
    /**
     * @return \ClickHouseDB\Cluster
     */
    public function getClickHouseCluster()
    {
            return $this->_cluster;
    }

    public function msg($text)
    {
            echo $text."\n";
    }

    private function cleanTable($dbt)
    {

        $sizes=$this->getClickHouseCluster()->getSizeTable($dbt);
        $this->msg("Clean table : $dbt,size = ".$this->humanFileSize($sizes));

        // split string "DB.TABLE"
        list($db,$table)=explode('.',$dbt);

        // Get Master node for table
        $nodes=$this->getClickHouseCluster()->getMasterNodeForTable($dbt);
        foreach ($nodes as $node)
        {
            $client=$this->getClickHouseCluster()->client($node);

            $size=$client->database($db)->tableSize($table);

            $this->msg("$node \t {$size['size']} \t {$size['min_date']} \t {$size['max_date']}");

            $client->dropOldPartitions($table,30,30);
        }
    }

    public function clean()
    {
        $this->msg("clean");

        $this->getClickHouseCluster()->setScanTimeOut(2.5); // 2500 ms
        $this->getClickHouseCluster()->setSoftCheck(true);
        if (!$this->getClickHouseCluster()->isReplicasIsOk())
        {
            throw new Exception('Replica state is bad , error='.$this->getClickHouseCluster()->getError());
        }

        $this->cleanTable('model.history_full_model_sharded');

        $this->cleanTable('model.history_model_result_sharded');
    }
}

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
$cl->activeClient()->setTimeout(0.01);
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
    <env name="CLICKHOUSE_PASSWORD" value="" />
    <env name="CLICKHOUSE_TMPPATH" value="/tmp" />
</php>
```

Run test
```bash

./vendor/bin/phpunit

./vendor/bin/phpunit --group ClientTest


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

### 2018-07-02 [Release 1.1.1]
* #47 Bindings wrong work - fix


### 2018-07-02 [Release 1.1.0]


New:
* `$client->getServerUptime()` Returns the server's uptime in seconds.
* `$client->getServerSystemSettings()` Read system.settings table and return array
* `$client->streamWrite()` function
* `$client->streamRead()` function


Warning:
* Now default enable`HttpCompression` set true
* Deprecated `StreamInsert` class

Fix:
* Fix `rawData()` result in `JSONCompact & JSONEachRow` format
* Fix Statement - unnecessary memory usage
* Fix support php5.6



### 2018-06-29 [Release 1.0.1]
* Do not convert int parameters in array to string in Bindings [pull 67](https://github.com/smi2/phpClickHouse/pull/67)
*

### 2018-06-25 [Release 1.0.0]
* Use Semantic versioning


### 2018-06-22

* Fix `tableSize('name')` and `tablesSize()`



### 2018-06-19
* Add DataTime Interface for Bind
* Fix phpDoc
* `Composer->require->"php": ">=5.6"`


### 2018-05-09
* Move `\ClickHouseDB\WhereInFile` to `\ClickHouseDB\Query\WhereInFile`
* Move `\ClickHouseDB\QueryException` to `\ClickHouseDB\Exception\QueryException`
* Move `\ClickHouseDB\DatabaseException` to `ClickHouseDB\Exception\DatabaseException`
* Move `\ClickHouseDB\FormatLine` to `\ClickHouseDB\Quote\FormatLine`
* Move `\ClickHouseDB\WriteToFile` to `ClickHouseDB\Query\WriteToFile`
* Move `\Curler\Request` to `\ClickHouseDB\Transport\CurlerRequest`
* Move `\Curler\CurlerRolling` to `\ClickHouseDB\Transport\CurlerRolling`
* Up to php 7.2 & phpunit 7.1 for Dev & Prs4 Autoloading



### 2018-03-26

* Fix StreamInsert : one stream work faster and safe than loop #PR43
* Fix cluster->clientLike()

### 2017-12-28

* Fix `FORMAT JSON` if set FORMAT in sql
* GetRaw() - result raw response if not json ``SELECT number as format_id FROM system.numbers LIMIT 3 FORMAT CSVWithNames``

### 2017-12-22

* progressFunction()
* Escape values

### 2017-12-12

* Not set `FORMAT JSON` if set FORMAT in sql

### 2017-11-22

- Add insertAssocBulk

### 2017-08-25

- Fix tablesSize(), use database filter
- Fix partitions(), use database filter

### 2017-08-14

- Add session_id support

### 2017-02-20

- Build composer 0.17.02

### 2016-12-09

- for ReadOnly users need set : `client->setReadOnlyUser(true);` or `$confi['readonly']` , see exam19_readonly_user.php

###  2016-11-25

- `client->truncateTable('tableName')`
- `cluster->getMasterNodeForTable('dbName.tableName') // node have is_leader=1`
- `cluster->getSizeTable('dbName.tableName')`
- `cluster->getTables()`
- `cluster->truncateTable('dbName.tableName')`
- See example cluster_06_truncate_table.php

###  2016-11-24

- add `cluster->setSoftCheck()`
- insertBatchFiles() support `$file_names` - string or array , `$columns_array` - array or null
- add insertBatchStream() return `\Curler\Request` no exec
- writeStreamData() return `\Curler\Request`
- fix httpCompression(false)
- getHeaders() as array from `\Curler\Request`
- `setReadFunction( function() )` in `Request`
- Add class StreamInsert, direct read from stream_resource to clickhouse:stream

###  2016-11-04

- add `$db->insertBatchTSVFiles()`,
- add format param in `$db->insertBatchFiles(,,,format)`,
- deprecated class CSV
- Add static class `\ClickHouseDB\FormatLine:CSV(),\ClickHouseDB\FormatLine:TSV(),\ClickHouseDB\FormatLine:Insert()`
- CSV RFC4180 - `\ClickHouseDB\FormatLine::CSV(Array))."\n"`
- Update exam12_array.php + unit tests

###  2016-11-03

- `$db->enableLogQueries(true)` - write to system.query_log
- `$db->enableExtremes(true);` - default extremes now, disabled
- `$db->isExists($database,$table)`

###  2016-10-27

- add Connect timeout , $db->setConnectTimeOut(5);
- change default ConnectTimeOut = 5 seconds. before 1 sec.
- change DNS_CACHE default to 120 seconds

###  2016-10-25 Release 0.16.10

- fix timeout error and add test

###  2016-10-23

- client->setTimeout($seconds)
- cluster->clientLike($cluster,$ip_addr_like)
- Delete all migration code from driver, move to https://github.com/smi2/phpMigrationsClickhouse

###  2016-09-20 Release 0.16.09

- Version/Release names: [ zero dot year dot month]
- Support cluster: new class Cluster and ClusterQuery
- output_format_write_statistics, for clickhouse version > v1.1.54019-stable
- WriteToFile in select,selectAsync
- Degeneration for Bindings & Conditions
- $db->select(new Query("Select..."));
- remove findActiveHostAndCheckCluster , clusterHosts , checkServerReplicas
- Add cleanQueryDegeneration(),addQueryDegeneration()
- Need $db->enableQueryConditions(); for use Conditions ; default Conditions - disabled;
- float in CurlerRequest->timeOut(2.5) = 2500 ms
- tablesSize() - add `sizebytes`


### 2016-08-11 Release 0.2.0

- exception on error write

### 2016-08-06 Release 0.1.0

- init
