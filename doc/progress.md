# Progress Function

Monitor query execution progress in real-time via HTTP headers.

## Usage

```php
$db->progressFunction(function ($data) {
    echo json_encode($data) . "\n";
});
```

Works for **both SELECT and INSERT/WRITE** operations.

## SELECT Progress

```php
$db->progressFunction(function ($data) {
    echo sprintf(
        "Read: %s rows, %s bytes\n",
        $data['read_rows'] ?? 0,
        $data['read_bytes'] ?? 0
    );
});

$st = $db->select('SELECT number, sleep(0.1) FROM system.numbers LIMIT 100');

// Output:
// Read: 10 rows, 80 bytes
// Read: 20 rows, 160 bytes
// Read: 30 rows, 240 bytes
// ...
```

## INSERT/WRITE Progress

```php
$db->progressFunction(function ($data) {
    echo sprintf(
        "Written: %s rows, %s bytes\n",
        $data['written_rows'] ?? 0,
        $data['written_bytes'] ?? 0
    );
});

// Insert a large batch
$rows = [];
for ($i = 0; $i < 100000; $i++) {
    $rows[] = [$i, "item_$i"];
}
$db->insert('my_table', $rows, ['id', 'name']);
```

## Progress Data Format

The callback receives an associative array with these fields:

| Field | Description |
|-------|-------------|
| `read_rows` | Number of rows read so far |
| `read_bytes` | Number of bytes read so far |
| `written_rows` | Number of rows written (INSERT) |
| `written_bytes` | Number of bytes written (INSERT) |
| `total_rows_to_read` | Total rows to read (if known) |

## Settings

`progressFunction()` automatically enables:

| Setting | Value | Purpose |
|---------|-------|---------|
| `send_progress_in_http_headers` | 1 | Enable progress headers |
| `http_headers_progress_interval_ms` | 100 | Update interval (ms) |
| `wait_end_of_query` | 1 | Required for write progress |

You can customize the interval before calling `progressFunction()`:

```php
$db->settings()->set('http_headers_progress_interval_ms', 500); // every 500ms
$db->progressFunction($callback);
```
