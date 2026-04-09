# Per-Query Settings

Override ClickHouse settings for individual queries without changing global configuration.

## Usage

All query methods accept an optional `$querySettings` array as the last parameter:

```php
// SELECT with extended timeout for one heavy query
$result = $db->select(
    'SELECT * FROM huge_table',
    [],       // bindings
    null,     // whereInFile
    null,     // writeToFile
    ['max_execution_time' => 300, 'max_rows_to_read' => 1000000]
);

// Next query uses the global timeout again (e.g. 30 seconds)
$db->select('SELECT 1');
```

## Supported Methods

```php
// select()
$db->select($sql, $bindings, $whereInFile, $writeToFile, $querySettings);

// selectAsync()
$db->selectAsync($sql, $bindings, $whereInFile, $writeToFile, $querySettings);

// write()
$db->write($sql, $bindings, $exception, $querySettings);

// selectWithParams()
$db->selectWithParams($sql, $params, $querySettings);

// writeWithParams()
$db->writeWithParams($sql, $params, $exception, $querySettings);

// readWithParams()
$db->readWithParams($streamRead, $sql, $params, $querySettings);
```

## Examples

### Heavy analytical query

```php
$result = $db->select(
    'SELECT user_id, count() FROM events GROUP BY user_id',
    [],
    null,
    null,
    [
        'max_execution_time' => 600,
        'max_memory_usage' => 10000000000,  // 10 GB
        'max_rows_to_read' => 100000000,
    ]
);
```

### Async insert (fire and forget)

```php
$db->write(
    "INSERT INTO buffer_table VALUES (1, 'data')",
    [],
    true,
    [
        'async_insert' => 1,
        'wait_for_async_insert' => 0,
    ]
);
```

### Read from specific replica

```php
$result = $db->select(
    'SELECT * FROM distributed_table',
    [],
    null,
    null,
    [
        'prefer_localhost_replica' => 0,
        'max_replica_delay_for_distributed_queries' => 300,
    ]
);
```

### Mutations with sync wait

```php
$db->write(
    'ALTER TABLE t DELETE WHERE id = :id',
    ['id' => 42],
    true,
    ['mutations_sync' => 1]
);
```

## How It Works

Per-query settings are merged with global settings at URL level. Per-query values take priority. Global settings are never modified.

```
Global: max_execution_time=30, enable_http_compression=1
Query:  max_execution_time=300

Result URL: ?max_execution_time=300&enable_http_compression=1
```
