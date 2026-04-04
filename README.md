# phpClickHouse

[![Downloads](https://poser.pugx.org/smi2/phpClickHouse/d/total.svg)](https://packagist.org/packages/smi2/phpClickHouse)
[![Packagist](https://poser.pugx.org/smi2/phpClickHouse/v/stable.svg)](https://packagist.org/packages/smi2/phpClickHouse)
[![Licence](https://poser.pugx.org/smi2/phpClickHouse/license.svg)](https://packagist.org/packages/smi2/phpClickHouse)

**[Documentation Site](https://smi2.github.io/phpClickHouse/)**

PHP client for [ClickHouse](https://clickhouse.com) — fast, lightweight, no dependencies beyond ext-curl.

## Features

- Sync & async (parallel) SELECT queries
- [Native query parameters](doc/native-params.md) — server-side `{name:Type}` binding, SQL injection impossible
- [Rich type system](doc/types.md) — Int64, Decimal, UUID, IPv4/IPv6, DateTime64, Date32, Map, Tuple
- Bulk inserts: arrays, CSV files, streams
- [Generators](doc/generators.md) — memory-efficient iteration for large resultsets
- HTTP compression (gzip) for inserts
- [Parameter bindings](doc/bindings.md) & SQL templates
- [Per-query settings](doc/per-query-settings.md) — override ClickHouse settings per request
- [Cluster support](doc/cluster.md) — auto-discovery, health checks, replicas
- [Streaming](doc/streaming.md) read/write with closures
- [Progress callbacks](doc/progress.md) for SELECT and INSERT
- [Structured exceptions](doc/exceptions.md) — ClickHouse error name, query ID
- Sessions, write-to-file, [INSERT statistics](doc/summary.md)
- HTTPS, SSL CA, IPv6 support
- Multiple auth methods (none, header, basic auth, query string)

## Requirements

- PHP 8.0+
- ext-curl
- ext-json

**Version compatibility:**

| PHP | phpClickHouse |
|-----|---------------|
| 5.6 | `<= 1.1.2` |
| 7.2 | `<= 1.3.10` |
| 7.3 | `1.4.x – 1.5.x` |
| 8.0+ | `>= 1.6.0` |

## Installation

```bash
composer require smi2/phpclickhouse
```

## Quick Start

```php
$db = new ClickHouseDB\Client([
    'host'     => '127.0.0.1',
    'port'     => '8123',
    'username' => 'default',
    'password' => '',
]);

$db->database('default');
$db->setTimeout(10);
$db->setConnectTimeOut(5);
$db->ping(true); // throws exception on failure
```

### Select

```php
$statement = $db->select('SELECT * FROM my_table WHERE id = :id', ['id' => 42]);

$statement->rows();      // all rows
$statement->fetchOne();  // first row
$statement->count();     // row count
```

### Native Query Parameters

```php
// Server-side typed binding — SQL injection impossible at protocol level
$result = $db->selectWithParams(
    'SELECT * FROM users WHERE id = {id:UInt32} AND name = {name:String}',
    ['id' => 42, 'name' => 'Alice']
);
```

### Insert

```php
$db->insert('my_table',
    [
        [time(), 'key1', 100],
        [time(), 'key2', 200],
    ],
    ['event_time', 'key', 'value']
);
```

### Generator (large resultsets)

```php
// Memory-efficient — one row at a time, no OOM
foreach ($db->selectGenerator('SELECT * FROM huge_table') as $row) {
    processRow($row);
}
```

### Write (DDL)

```php
$db->write('CREATE TABLE IF NOT EXISTS my_table (id UInt32, name String) ENGINE = MergeTree ORDER BY id');
$db->write('DROP TABLE IF EXISTS my_table');
```

## Documentation

Detailed guides with examples are available in the [doc/](doc/) directory:

### Core
- **[Quick Start & Basics](doc/basics.md)** — connection, select, insert, write, Statement API
- **[Async Queries](doc/async.md)** — parallel selects, batch file inserts, error handling
- **[Bindings & Conditions](doc/bindings.md)** — parameter binding, SQL templates, conditions
- **[Settings & Configuration](doc/settings.md)** — timeouts, compression, HTTPS, auth methods, sessions

### Advanced
- **[Native Query Parameters](doc/native-params.md)** — server-side `{name:Type}` binding
- **[ClickHouse Types](doc/types.md)** — Int64, Decimal, UUID, IPv4/IPv6, DateTime64, Date32, Map, Tuple
- **[Generators](doc/generators.md)** — memory-efficient `selectGenerator()` for large resultsets
- **[Per-Query Settings](doc/per-query-settings.md)** — override settings per request
- **[Streaming](doc/streaming.md)** — streamRead, streamWrite, closures, gzip
- **[Cluster](doc/cluster.md)** — multi-node setup, replicas, truncate, master node

### Reference
- **[Structured Exceptions](doc/exceptions.md)** — error codes, exception names, query ID
- **[Progress Function](doc/progress.md)** — real-time progress for SELECT and INSERT
- **[INSERT Statistics](doc/summary.md)** — written_rows, written_bytes via X-ClickHouse-Summary
- **[Advanced Features](doc/advanced.md)** — partitions, table sizes, write-to-file, logging, debug

## Development

```bash
# Start both ClickHouse versions (21.9 + 26.3)
docker-compose -f tests/docker-compose.yaml up -d

# Run tests against ClickHouse 21.9
./vendor/bin/phpunit -c phpunit-ch21.xml

# Run tests against ClickHouse 26.3
./vendor/bin/phpunit -c phpunit-ch26.xml

# Static analysis (PHPStan level 5)
./vendor/bin/phpstan analyse --memory-limit=512M

# Code style
./vendor/bin/phpcs
```

See [CLAUDE.md](CLAUDE.md) for project architecture and contribution guidelines.

## Articles

- [habr.com — phpClickHouse (part 1)](https://habrahabr.ru/company/smi2/blog/317682/)
- [habr.com — phpClickHouse (part 2)](https://habr.com/company/smi2/blog/314558/)

## License

MIT — see [LICENSE](LICENSE)

## Changelog

See [CHANGELOG.md](CHANGELOG.md)
