php ClickHouse wrapper
===================

Connect and select database:
```php
$config=['host'=>'192.168.1.1','port'=>'8123','username'=>'default','password'=>''];
$db=new ClickHouseDB\Client($config);
$db->database('default');
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
) ENGINE = SummingMergeTree(event_date, (site_id,site_key, event_time, event_date), 8192)
';
```

Insert data:
```php
$stat=$db->insert('summing_url_views',
    [
        [time(),'HASH1',2345,22,20,2],
        [time(),'HASH2',2345,12,9,3],
        [time(),'HASH3',5345,33,33,0],
        [time(),'HASH3',5345,55,0,55],
    ]
    ,
    ['event_time','site_key','site_id','views','v_00','v_55']
);
```

Select:
```php
$statement=$db->select('SELECT * FROM summing_url_views LIMIT 2');
```

Work with Statement:
```php
//Count select rows
$statement->count()
//Count all rows
$statement->countAll()
// fetch one row
$statement->fetchOne()
//get extremes min
print_r($statement->extremesMin());
//totals row
print_r($statement->totals());
//result all
print_r($statement->rows());
//totalTimeRequest
print_r($statement->totalTimeRequest());
```

Select result as tree:
```php
$statement=$db->select('SELECT event_date,site_key,sum(views),avg(views) FROM summing_url_views WHERE site_id<3333 GROUP BY event_date,url_hash WITH TOTALS');
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
$db->write("DROP TABLE IF EXISTS summing_url_views");
```


Features
----
### Select parallel queries (asynchronous)
```php
$state1=$db->selectAsync('SELECT 1 as ping');
$state2=$db->selectAsync('SELECT 2 as ping');
//run
$db->executeAsync();
//result
print_r($state1->rows());
print_r($state2->fetchOne('ping'));
```

### Parallelizing massive inserts from CSV file
```php
$file_data_names=[
    '/tmp/clickHouseDB_test.1.data',
    '/tmp/clickHouseDB_test.2.data',
    '/tmp/clickHouseDB_test.3.data',
    '/tmp/clickHouseDB_test.4.data',
    '/tmp/clickHouseDB_test.5.data',
];
// insert all files
$stat=$db->insertBatchFiles('summing_url_views',
   $file_data_names,
   ['event_time','site_key','site_id','views','v_00','v_55']
);
```
### Parallelizing errors

selectAsync without executeAsync

```php
$select=$db->selectAsync('SELECT * FROM summing_url_views LIMIT 1');
$insert=$db->insertBatchFiles('summing_url_views',['/tmp/clickHouseDB_test.1.data'],['event_time']);
// 'Exception' with message 'Queue must be empty, before insertBatch,need executeAsync'
```
see example/exam5_error_async.php



### Find active host and check cluster

We use in the smi2, DNS Round-Robin.
Set host =  "clickhouse.smi2.ru" is A record  => [ xdb1.ch1.smi2.ru,xdb1.ch2.smi2.ru,xdb1.ch3.smi2.ru....]

function findActiveHostAndCheckCluster() - ping all IPs in DNS record
then random() select from active list
if dev. server (one IP or host) - no check
see example/exam6_check_cluster.php

```php
$db=new ClickHouseDB\Client($config);
$change_host=true;
$time_out_second=1;
list($resultGoodHost,$resultBadHost,$selectHost)=$db->findActiveHostAndCheckCluster($time_out_second,$change_host);
echo "SelectHost:".$selectHost."\n";

```




### Simple sql conditions & template

```php
$input_params=[
  'select_date'=>['2000-10-10','2000-10-11','2000-10-12'],
  'limit'=>5,
  'from_table'=>'table'
];
$select='
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

$statement=$db->selectAsync($select,$input_params);
echo $statement->sql();
/*
SELECT * FROM table
WHERE
event_date IN ('2000-10-10','2000-10-11','2000-10-12')
LIMIT 5
FORMAT JSON
*/


$input_params['select_date']=false;
$statement=$db->selectAsync($select,$input_params);
echo $statement->sql();
/*
SELECT * FROM table
WHERE
event_date=today()
LIMIT 5
FORMAT JSON
*/


$state1=$db->selectAsync('SELECT 1 as {key} WHERE {key}=:value',['key'=>'ping','value'=>1]);
// SELECT 1 as ping WHERE ping="1"
```




### Todos


 - Write Tests
 - Write docs
 - Fix array insert in row
 - Normal exception
 - add/use composer ?
 - drop include ?
 - find ActiveHost & CheckCluster - how check cluster and replica ?

License
----

GPL


