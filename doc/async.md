# Async Queries

## Parallel SELECT

```php
$state1 = $db->selectAsync('SELECT 1 as ping');
$state2 = $db->selectAsync('SELECT 2 as ping');

// Execute all queued queries
$db->executeAsync();

// Access results
print_r($state1->rows());
print_r($state2->fetchOne('ping'));
```

## Parallel CSV inserts

```php
$file_data_names = [
    '/tmp/clickHouseDB_test.1.data',
    '/tmp/clickHouseDB_test.2.data',
    '/tmp/clickHouseDB_test.3.data',
    '/tmp/clickHouseDB_test.4.data',
    '/tmp/clickHouseDB_test.5.data',
];

$stat = $db->insertBatchFiles(
    'summing_url_views',
    $file_data_names,
    ['event_time', 'site_key', 'site_id', 'views', 'v_00', 'v_55']
);
```

## Error handling

The queue must be empty before calling `insertBatchFiles`. If you mix async selects with batch inserts without executing first, you'll get an exception:

```php
$select = $db->selectAsync('SELECT * FROM summing_url_views LIMIT 1');
$insert = $db->insertBatchFiles('summing_url_views', ['/tmp/clickHouseDB_test.1.data'], ['event_time']);
// Exception: 'Queue must be empty, before insertBatch, need executeAsync'
```

Always call `$db->executeAsync()` before starting batch inserts.

## Gzip compression

Compress CSV files on the fly with `zlib.deflate`:

```php
$db->settings()->max_execution_time(200);
$db->enableHttpCompression(true);

$result_insert = $db->insertBatchFiles('summing_url_views', $file_data_names, [...]);

foreach ($result_insert as $fileName => $state) {
    echo $fileName . ' => ' . json_encode($state->info_upload()) . PHP_EOL;
}
```

See speed test in `example/exam08_http_gzip_batch_insert.php`.
