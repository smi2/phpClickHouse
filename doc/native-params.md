# Native Query Parameters

ClickHouse supports server-side typed parameter binding via the HTTP protocol. Parameters use `{name:Type}` syntax in SQL — the server parses values, making SQL injection impossible at the protocol level.

## Basic Usage

```php
// SELECT with typed parameters
$result = $db->selectWithParams(
    'SELECT {p1:UInt32} + {p2:UInt32} as sum',
    ['p1' => 3, 'p2' => 4]
);
echo $result->fetchOne('sum'); // 7

// INSERT with typed parameters
$db->writeWithParams(
    'INSERT INTO users VALUES ({id:UInt32}, {name:String}, {email:String})',
    ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']
);
```

## Parameter Types

Any ClickHouse type can be used in the `{name:Type}` placeholder:

```php
// Integers
$db->selectWithParams('SELECT {n:Int32} as n', ['n' => -42]);
$db->selectWithParams('SELECT {n:UInt64} as n', ['n' => 18446744073709551615]);

// Strings
$db->selectWithParams('SELECT {s:String} as s', ['s' => "Hello 'World'"]);

// Floats
$db->selectWithParams('SELECT {f:Float64} as f', ['f' => 3.14159]);

// Bool
$db->selectWithParams('SELECT {flag:Bool} as flag', ['flag' => true]);

// Nullable
$db->selectWithParams('SELECT {val:Nullable(String)} as val', ['val' => null]);

// DateTime
$db->selectWithParams(
    'SELECT {dt:DateTime} as dt',
    ['dt' => new DateTime('2024-01-15 10:30:00')]
);

// DateTime64
$db->selectWithParams(
    'SELECT {dt:DateTime64(3)} as dt',
    ['dt' => DateTime64::fromString('2024-01-15 10:30:00.123')]
);

// UUID
$db->selectWithParams(
    'SELECT {id:UUID} as id',
    ['id' => UUID::fromString('6d38d288-5b13-4714-b6e4-faa59ffd49d8')]
);

// Array
$db->selectWithParams(
    'SELECT {arr:Array(UInt32)} as arr',
    ['arr' => [1, 2, 3]]
);

// IPv4 / IPv6
$db->selectWithParams(
    'SELECT {ip:IPv4} as ip',
    ['ip' => IPv4::fromString('192.168.1.1')]
);
```

## Streaming with Native Parameters

Use `readWithParams()` to stream query results while still using server-side typed parameters:

```php
$stream = fopen('php://memory', 'r+');
$streamRead = new ClickHouseDB\Transport\StreamRead($stream);

$db->readWithParams(
    $streamRead,
    'SELECT id, name FROM users WHERE id = {id:UInt32} FORMAT JSONEachRow',
    ['id' => 1]
);

rewind($stream);
while (($line = fgets($stream)) !== false) {
    $row = json_decode(trim($line), true);
    echo $row['name'];
}
fclose($stream);
```

The FORMAT clause must be included in the SQL string — unlike `selectWithParams()`, no default format is applied.

## Per-Query Settings

All three methods accept an optional settings override:

```php
$result = $db->selectWithParams(
    'SELECT {n:UInt32} as n',
    ['n' => 1],
    ['max_execution_time' => 300]
);

$db->writeWithParams(
    'INSERT INTO t VALUES ({id:UInt32})',
    ['id' => 1],
    true,
    ['async_insert' => 1, 'wait_for_async_insert' => 0]
);

$stream = fopen('php://memory', 'r+');
$db->readWithParams(
    new ClickHouseDB\Transport\StreamRead($stream),
    'SELECT id FROM t WHERE id = {id:UInt32} FORMAT JSONEachRow',
    ['id' => 1],
    ['max_execution_time' => 300]
);
```

## Native Params vs Bindings

| Feature | Native `{name:Type}` | Bindings `:name` |
|---------|----------------------|-------------------|
| SQL injection protection | Server-side (protocol level) | Client-side (escaping) |
| Type validation | Server validates types | No validation |
| Syntax | `{name:Type}` in SQL | `:name` or `{name}` in SQL |
| Method | `selectWithParams()`, `writeWithParams()`, `readWithParams()` | `select()` |
| Large values | Passed in URL params | Embedded in SQL body |

**Recommendation:** Use native parameters for new code. They are safer and let the server handle type conversion.

## How It Works

Under the hood, `selectWithParams()` sends:
- SQL as the `query` URL parameter
- Each param as `param_name=value` URL parameter
- ClickHouse server parses `{name:Type}` and substitutes from URL params

```
POST /?query=SELECT+{p1:UInt32}+as+n&param_p1=42
```
