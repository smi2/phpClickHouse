# Quick Start & Basics

## Connection

```php
$config = [
    'host'     => '192.168.1.1',
    'port'     => '8123',
    'username' => 'default',
    'password' => '',
    'https'    => true,
];

$db = new ClickHouseDB\Client($config);
$db->database('default');
$db->setTimeout(10);       // seconds
$db->setConnectTimeOut(5); // seconds
$db->ping(true);           // throws exception on failure
```

### Connection without port

```php
$config['host'] = 'blabla.com';
$config['port'] = 0;
// getUri() === 'http://blabla.com'

$config['host'] = 'blabla.com/urls';
$config['port'] = 8765;
// getUri() === 'http://blabla.com/urls'

$config['host'] = 'blabla.com:2224';
$config['port'] = 1234;
// getUri() === 'http://blabla.com:2224'
```

### ReadOnly user

```php
$config = [
    'host'     => '192.168.1.20',
    'port'     => '8123',
    'username' => 'ro',
    'password' => 'ro',
    'readonly' => true,
];
```

## Show tables

```php
print_r($db->showTables());
```

## Create table

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

### Show create table

```php
echo $db->showCreateTable('summing_url_views');
```

## Insert data

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

### UInt64 values

```php
use ClickHouseDB\Type\UInt64;

$statement = $db->insert('table_name',
    [
        [time(), UInt64::fromString('18446744073709551615')],
    ],
    ['event_time', 'uint64_type_column']
);
```

### Insert Assoc Bulk

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

$db->insertAssocBulk([$oneRow, $oneRow, $failRow]);
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
```

#### FormatLine helper

```php
// CSV format
var_dump(ClickHouseDB\Quote\FormatLine::CSV(['HASH1', ["a", "dddd", "xxx"]]));

// TSV format
var_dump(ClickHouseDB\Quote\FormatLine::TSV(['HASH1', ["a", "dddd", "xxx"]]));

// Write to file
$row = ['event_time' => date('Y-m-d H:i:s'), 'arr1' => [1,2,3], 'arrs' => ["A", "B\nD\nC"]];
file_put_contents($fileName, ClickHouseDB\Quote\FormatLine::TSV($row) . "\n", FILE_APPEND);
```

## Select

```php
$statement = $db->select('SELECT * FROM summing_url_views LIMIT 2');
```

## Statement API

```php
$statement->count();          // select row count
$statement->countAll();       // total row count
$statement->fetchOne();       // first row
$statement->extremesMin();    // extremes min
$statement->totals();         // totals row
$statement->rows();           // all rows
$statement->totalTimeRequest(); // time
$statement->rawData();        // raw JSON decoded array
$statement->responseInfo();   // raw curl_info
$statement->info();           // human-readable size info

// Statistics (clickhouse-server >= 54011)
$db->settings()->set('output_format_write_statistics', true);
print_r($statement->statistics());
```

### Iterator

```php
$state = $db->select('SELECT (number+1) as nnums FROM system.numbers LIMIT 5');
foreach ($state as $key => $value) {
    echo $value['nnums'];
}
```

### Result as tree

```php
$statement = $db->select('
    SELECT event_date, site_key, sum(views), avg(views)
    FROM summing_url_views
    WHERE site_id < 3333
    GROUP BY event_date, site_key
    WITH TOTALS
');

print_r($statement->rowsAsTree('event_date.site_key'));

/*
[2016-07-18] => [
    [HASH2] => [event_date => 2016-07-18, site_key => HASH2, sum(views) => 12, avg(views) => 12],
    [HASH1] => [event_date => 2016-07-18, site_key => HASH1, sum(views) => 22, avg(views) => 22],
]
*/
```

## Drop table

```php
$db->write('DROP TABLE IF EXISTS summing_url_views');
```

## Check existence

```php
$db->isExists($database, $table);
```
