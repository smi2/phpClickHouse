# ClickHouse Types

The library provides PHP classes for ClickHouse-specific types that don't have native PHP equivalents. Use them when inserting data that requires precise type control.

All types implement `ClickHouseDB\Type\Type` interface and work with `insert()`, bindings (`:param`), and native parameters (`{name:Type}`).

## Numeric Types

### UInt64

Large unsigned integers that overflow PHP's `int` range.

```php
use ClickHouseDB\Type\UInt64;

$db->insert('table', [
    [UInt64::fromString('18446744073709551615')],
], ['big_number']);
```

### Int64

Large signed integers.

```php
use ClickHouseDB\Type\Int64;

$db->insert('table', [
    [Int64::fromString('-9223372036854775808')],
    [Int64::fromString('9223372036854775807')],
], ['value']);
```

### Decimal

Exact decimal numbers — no floating-point rounding.

```php
use ClickHouseDB\Type\Decimal;

$db->insert('table', [
    [Decimal::fromString('12345.6789')],
    [Decimal::fromString('-99999.9999')],
], ['price']);
```

## Date & Time Types

### DateTime64

Sub-second precision timestamps (milliseconds, microseconds, nanoseconds).

```php
use ClickHouseDB\Type\DateTime64;

// From string
$db->insert('table', [
    [DateTime64::fromString('2024-01-15 10:30:00.123')],
], ['created_at']);

// From PHP DateTimeInterface (precision = 3 for milliseconds)
$dt = new DateTimeImmutable('2024-06-15 12:00:00.456789');
$db->insert('table', [
    [DateTime64::fromDateTime($dt, 3)],  // → '2024-06-15 12:00:00.456'
], ['created_at']);

// Precision options: 1-9 (1=tenths, 3=ms, 6=μs, 9=ns)
DateTime64::fromDateTime($dt, 6);  // → '2024-06-15 12:00:00.456789'
```

### Date32

Extended date range (1900-01-01 to 2299-12-31), unlike Date which is limited to 2149.

```php
use ClickHouseDB\Type\Date32;

// From string
$db->insert('table', [
    [Date32::fromString('2024-01-15')],
], ['event_date']);

// From PHP DateTimeInterface
$db->insert('table', [
    [Date32::fromDateTime(new DateTimeImmutable('2250-12-31'))],
], ['future_date']);
```

## Network Types

### IPv4

```php
use ClickHouseDB\Type\IPv4;

$db->insert('table', [
    [IPv4::fromString('192.168.1.1')],
    [IPv4::fromString('10.0.0.1')],
], ['ip']);
```

### IPv6

```php
use ClickHouseDB\Type\IPv6;

$db->insert('table', [
    [IPv6::fromString('::1')],
    [IPv6::fromString('2001:db8::1')],
], ['ip']);
```

## String Types

### UUID

```php
use ClickHouseDB\Type\UUID;

$uuid = '6d38d288-5b13-4714-b6e4-faa59ffd49d8';

$db->insert('table', [
    [UUID::fromString($uuid)],
], ['id']);

// Select back
$st = $db->select('SELECT id FROM table');
echo $st->fetchOne('id'); // '6d38d288-5b13-4714-b6e4-faa59ffd49d8'
```

## Boolean Type

### Boolean

ClickHouse `Bool` columns. Stored as `1`/`0` strings internally.

```php
use ClickHouseDB\Type\Boolean;

// From bool
$db->insert('table', [
    [Boolean::fromBool(true)],
    [Boolean::fromBool(false)],
], ['is_active']);

// From string
$db->insert('table', [
    [Boolean::fromString('1')],
], ['is_active']);
```

## Composite Types

### MapType

Key-value maps for `Map(K, V)` columns.

```php
use ClickHouseDB\Type\MapType;

$db->write("CREATE TABLE t (data Map(String, String)) ENGINE = Memory");

$db->insert('t', [
    [MapType::fromArray(['key1' => 'val1', 'key2' => 'val2'])],
], ['data']);
```

### TupleType

Fixed-length tuples for `Tuple(T1, T2, ...)` columns.

```php
use ClickHouseDB\Type\TupleType;

$db->write("CREATE TABLE t (point Tuple(Float64, Float64)) ENGINE = Memory");

$db->insert('t', [
    [TupleType::fromArray([55.7558, 37.6173])],  // Moscow coordinates
], ['point']);
```

## Using Types with Native Parameters

All types work with `selectWithParams()` / `writeWithParams()`:

```php
use ClickHouseDB\Type\UUID;
use ClickHouseDB\Type\IPv4;
use ClickHouseDB\Type\DateTime64;

// UUID
$st = $db->selectWithParams(
    'SELECT * FROM users WHERE id = {id:UUID}',
    ['id' => UUID::fromString('6d38d288-5b13-4714-b6e4-faa59ffd49d8')]
);

// IPv4
$st = $db->selectWithParams(
    'SELECT * FROM logs WHERE ip = {ip:IPv4}',
    ['ip' => IPv4::fromString('192.168.1.1')]
);

// DateTime64
$st = $db->selectWithParams(
    'SELECT * FROM events WHERE created_at > {since:DateTime64(3)}',
    ['since' => DateTime64::fromString('2024-01-01 00:00:00.000')]
);

// Array
$st = $db->selectWithParams(
    'SELECT * FROM t WHERE id IN {ids:Array(UInt32)}',
    ['ids' => [1, 2, 3]]
);
```

## Using Types with Bindings

Types also work with the classic `:param` binding syntax:

```php
use ClickHouseDB\Type\UInt64;

$st = $db->select(
    'SELECT * FROM table WHERE big_id = :id',
    ['id' => UInt64::fromString('18446744073709551615')]
);
```
