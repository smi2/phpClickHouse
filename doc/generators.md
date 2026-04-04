# Generators (Memory-Efficient Queries)

For large resultsets that don't fit in memory, use generators to process rows one at a time.

## selectGenerator()

Streams results from ClickHouse using JSONEachRow format and yields one row at a time. Unlike `select()->rows()`, the full resultset is never loaded into PHP memory.

```php
foreach ($db->selectGenerator('SELECT * FROM huge_table') as $row) {
    // $row is an associative array: ['column1' => value1, 'column2' => value2, ...]
    processRow($row);
}
```

### With Bindings

```php
foreach ($db->selectGenerator(
    'SELECT * FROM events WHERE date > :date',
    ['date' => '2024-01-01']
) as $row) {
    echo $row['event_name'] . "\n";
}
```

### With Per-Query Settings

```php
foreach ($db->selectGenerator(
    'SELECT * FROM huge_table',
    [],
    ['max_execution_time' => 600]
) as $row) {
    // process with extended timeout
}
```

### Count rows without loading all into memory

```php
$count = 0;
foreach ($db->selectGenerator('SELECT * FROM events') as $row) {
    $count++;
}
echo "Processed $count rows";
```

### Write to file row by row

```php
$fp = fopen('output.csv', 'w');
foreach ($db->selectGenerator('SELECT id, name, email FROM users') as $row) {
    fputcsv($fp, $row);
}
fclose($fp);
```

## rowsGenerator()

If you already have a `Statement` from `select()`, you can iterate over it with a generator instead of calling `rows()`:

```php
$st = $db->select('SELECT * FROM table LIMIT 1000');

// Instead of $st->rows() which returns the full array:
foreach ($st->rowsGenerator() as $row) {
    echo $row['id'] . "\n";
}
```

Note: `rowsGenerator()` still loads data in `init()` first. For true streaming from ClickHouse, use `selectGenerator()`.

## Comparison

| Method | Memory | Speed | Use case |
|--------|--------|-------|----------|
| `select()->rows()` | All rows in memory | Fast for small results | < 100K rows |
| `select()->rowsGenerator()` | All rows in memory (init) | Iterator interface | When you need Generator type |
| `selectGenerator()` | One row at a time | Best for large results | > 100K rows, ETL, exports |

## How selectGenerator() Works

1. Opens a `php://temp` stream
2. Calls `streamRead()` with `FORMAT JSONEachRow`
3. Reads the stream line by line
4. Decodes each JSON line and yields it
5. Closes the stream when done

The key difference from `select()`: JSONEachRow format produces one JSON object per line, so each line can be decoded independently without parsing the entire response.
