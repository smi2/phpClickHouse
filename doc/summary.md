# Insert Statistics & Summary

## The Problem

For SELECT queries, ClickHouse returns statistics (elapsed time, rows read, etc.) in the JSON response body. But for INSERT/write queries, the response body is empty — statistics are sent in the `X-ClickHouse-Summary` HTTP header instead.

## summary()

The `summary()` method reads the `X-ClickHouse-Summary` response header:

```php
$stat = $db->insert('my_table',
    [
        [1, 'a'],
        [2, 'b'],
        [3, 'c'],
    ],
    ['id', 'name']
);

// Get all summary data
print_r($stat->summary());
/*
[
    'read_rows' => '0',
    'read_bytes' => '0',
    'written_rows' => '3',
    'written_bytes' => '24',
    'total_rows_to_read' => '0',
]
*/

// Get specific key
echo $stat->summary('written_rows'); // '3'
echo $stat->summary('written_bytes'); // '24'
```

## statistics() fallback

The `statistics()` method now falls back to `summary()` when the body doesn't contain statistics (i.e., for INSERT queries):

```php
// Works for both SELECT and INSERT
$stat = $db->insert('my_table', $data, $columns);
$statistics = $stat->statistics(); // returns summary data for INSERT

$stat = $db->select('SELECT count() FROM my_table');
$statistics = $stat->statistics(); // returns body statistics for SELECT
```

## Available summary keys

| Key | Description |
|-----|-------------|
| `read_rows` | Number of rows read |
| `read_bytes` | Number of bytes read |
| `written_rows` | Number of rows written |
| `written_bytes` | Number of bytes written |
| `total_rows_to_read` | Total rows to read |

Note: available keys may vary by ClickHouse version. Older versions (< 22.x) may return zeros for write statistics.
