---
layout: default
title: Advanced Features
---

[< Back to Home](./)

# Advanced Features

## Table & database sizes

Results in human-readable sizes:

```php
print_r($db->databaseSize());
print_r($db->tablesSize());
print_r($db->tableSize('summing_partions_views'));
```

## Partitions

```php
$count_result = 2;
print_r($db->partitions('summing_partions_views', $count_result));
```

## Server info

```php
print_r($db->getServerUptime());
print_r($db->getServerSystemSettings());
print_r($db->getServerSystemSettings('merge_tree_min_rows_for_concurrent_read'));
```

## Progress function

Monitor query execution progress:

```php
$db->progressFunction(function ($data) {
    echo "CALL FUNCTION:" . json_encode($data) . "\n";
});

$st = $db->select('SELECT number, sleep(0.2) FROM system.numbers LIMIT 5');

// Output:
// CALL FUNCTION:{"read_rows":"2","read_bytes":"16","total_rows":"0"}
// CALL FUNCTION:{"read_rows":"3","read_bytes":"24","total_rows":"0"}
```

## Query logging

```php
$db->enableLogQueries();
$db->select('SELECT 1 as p');
print_r($db->select('SELECT * FROM system.query_log')->rows());
```

## Debug & verbose

```php
$db->verbose();
```

### Verbose to stream

```php
$cli = new ClickHouseDB\Client($config);
$cli->verbose();

$stream = fopen('php://memory', 'r+');
$cli->transport()->setStdErrOut($stream);

$st = $cli->select('SELECT 1 as ppp');
$st->rows();

fseek($stream, 0, SEEK_SET);
echo stream_get_contents($stream);
```
