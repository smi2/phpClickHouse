# Streaming

## streamWrite

Write data via a stream:

```php
$stream = fopen('php://memory', 'r+');

$streamWrite = new ClickHouseDB\Transport\StreamWrite($stream);

$client->streamWrite(
    $streamWrite,
    'INSERT INTO {table_name} FORMAT JSONEachRow',
    ['table_name' => '_phpCh_SteamTest']
);
```

### With custom closure & gzip

```php
$stream = fopen('php://memory', 'r+');

// Write JSON data to stream
for ($f = 0; $f < 23; $f++) {
    fwrite($stream, json_encode(['a' => $f]) . PHP_EOL);
}
rewind($stream);

$streamWrite = new ClickHouseDB\Transport\StreamWrite($stream);
$streamWrite->applyGzip(); // Enable gzip compression

// Custom read closure
$callable = function ($ch, $fd, $length) use ($stream) {
    return ($line = fread($stream, $length)) ? $line : '';
};

$streamWrite->closure($callable);

$r = $client->streamWrite(
    $streamWrite,
    'INSERT INTO {table_name} FORMAT JSONEachRow',
    ['table_name' => '_phpCh_SteamTest']
);

print_r($r->info_upload());
```

## streamRead

Read query results via a stream:

```php
$stream = fopen('php://memory', 'r+');
$streamRead = new ClickHouseDB\Transport\StreamRead($stream);

$r = $client->streamRead(
    $streamRead,
    'SELECT sin(number) as sin, cos(number) as cos FROM {table_name} LIMIT 4 FORMAT JSONEachRow',
    ['table_name' => 'system.numbers']
);

rewind($stream);
while (($buffer = fgets($stream, 4096)) !== false) {
    echo ">>> " . $buffer;
}
fclose($stream);
```

### With custom closure

```php
$stream = fopen('php://memory', 'r+');
$streamRead = new ClickHouseDB\Transport\StreamRead($stream);

$callable = function ($ch, $string) use ($stream) {
    // Transform data on the fly
    fwrite($stream, str_ireplace('"sin"', '"max"', $string));
    return strlen($string);
};

$streamRead->closure($callable);

$r = $client->streamRead(
    $streamRead,
    'SELECT sin(number) as sin, cos(number) as cos FROM {table_name} LIMIT 44 FORMAT JSONEachRow',
    ['table_name' => 'system.numbers']
);
```

## Direct write to file

Send ClickHouse results directly to a file without JSON parsing:

```php
$WriteToFile = new ClickHouseDB\WriteToFile('/tmp/_1_select.csv');
$db->select('SELECT * FROM summing_url_views', [], null, $WriteToFile);

// Async with different formats
$db->selectAsync('SELECT * FROM summing_url_views LIMIT 4', [], null,
    new ClickHouseDB\WriteToFile('/tmp/_3_select.tab', true, 'TabSeparatedWithNames')
);
$db->selectAsync('SELECT * FROM summing_url_views LIMIT 4', [], null,
    new ClickHouseDB\WriteToFile('/tmp/_4_select.tab', true, 'TabSeparated')
);
$statement = $db->selectAsync('SELECT * FROM summing_url_views LIMIT 54', [], null,
    new ClickHouseDB\WriteToFile('/tmp/_5_select.csv', true, ClickHouseDB\WriteToFile::FORMAT_CSV)
);
```
