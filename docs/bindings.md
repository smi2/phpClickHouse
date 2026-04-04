---
layout: default
title: Bindings & Conditions
---

[< Back to Home](./)

# Bindings & Conditions

## Parameter bindings

Two binding syntaxes are supported:
- `:paramName` — replaced with escaped value
- `{paramName}` — replaced with raw value (use for table/column names)

```php
$date1 = new DateTime("now");

$bindings = [
    'select_date' => ['2000-10-10', '2000-10-11', '2000-10-12'],
    'datetime'    => $date1,
    'limit'       => 5,
    'from_table'  => 'table',
];

$statement = $db->selectAsync(
    "SELECT * FROM {from_table} WHERE datetime = :datetime LIMIT {limit}",
    $bindings
);
```

### Double binding

Bindings are resolved iteratively:

```php
$keys = [
    'A' => '{B}',
    'B' => ':C',
    'C' => 123,
    'Z' => [':C', ':B', ':C'],
];

$db->selectAsync('{A} :Z', $keys)->sql();
// Result: "123 ':C',':B',':C' FORMAT JSON"
```

### Array bindings

Arrays are automatically expanded to comma-separated lists for use in `IN` clauses:

```php
$db->select(
    'SELECT * FROM table WHERE id IN (:ids)',
    ['ids' => [1, 2, 3]]
);
// WHERE id IN (1, 2, 3)
```

## SELECT WHERE IN (local CSV file)

```php
$whereIn = new ClickHouseDB\Query\WhereInFile();
$whereIn->attachFile(
    '/tmp/temp_csv.txt',
    'namex',
    ['site_id' => 'Int32', 'site_hash' => 'String'],
    ClickHouseDB\Query\WhereInFile::FORMAT_CSV
);

$result = $db->select($sql, [], $whereIn);
```

See `example/exam7_where_in.php`.

## SQL Conditions (deprecated)

Conditions must be explicitly enabled:

```php
$db->enableQueryConditions();
```

### If/else blocks

```php
$input_params = [
    'select_date' => ['2000-10-10', '2000-10-11', '2000-10-12'],
    'limit'       => 5,
    'from_table'  => 'table',
];

$select = '
    SELECT * FROM {from_table}
    WHERE
    {if select_date}
        event_date IN (:select_date)
    {else}
        event_date = today()
    {/if}
    {if limit}
    LIMIT {limit}
    {/if}
';

$statement = $db->selectAsync($select, $input_params);
echo $statement->sql();
/*
SELECT * FROM table
WHERE event_date IN ('2000-10-10','2000-10-11','2000-10-12')
LIMIT 5
FORMAT JSON
*/

// With select_date = false:
/*
SELECT * FROM table
WHERE event_date = today()
LIMIT 5
FORMAT JSON
*/
```

### Mixed bindings and conditions

```php
$state1 = $db->selectAsync(
    'SELECT 1 as {key} WHERE {key} = :value',
    ['key' => 'ping', 'value' => 1]
);
// SELECT 1 as ping WHERE ping = "1"
```

### Custom degeneration

See `example/exam16_custom_degeneration.php` for implementing custom query transformations:

```
SELECT {ifint VAR} result_if_intval_NON_ZERO {/if}
SELECT {ifint VAR} result_if_intval_NON_ZERO {else} BLA BLA {/if}
```
