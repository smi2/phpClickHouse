# phpClickHouse

[![Downloads](https://poser.pugx.org/smi2/phpClickHouse/d/total.svg)](https://packagist.org/packages/smi2/phpClickHouse)
[![Packagist](https://poser.pugx.org/smi2/phpClickHouse/v/stable.svg)](https://packagist.org/packages/smi2/phpClickHouse)
[![Licence](https://poser.pugx.org/smi2/phpClickHouse/license.svg)](https://packagist.org/packages/smi2/phpClickHouse)

PHP client for [ClickHouse](https://clickhouse.com) — fast, lightweight, no dependencies beyond ext-curl.

## Features

- Sync & async (parallel) SELECT queries
- Bulk inserts: arrays, CSV files, streams
- HTTP compression (gzip) for inserts
- Parameter bindings & SQL templates
- Cluster support: auto-discovery, health checks, replicas
- Streaming read/write with closures
- Sessions, progress callbacks, write-to-file
- HTTPS & SSL CA support
- Multiple auth methods (header, basic auth, query string)

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

### Write (DDL)

```php
$db->write('CREATE TABLE IF NOT EXISTS my_table (id UInt32, name String) ENGINE = MergeTree ORDER BY id');
$db->write('DROP TABLE IF EXISTS my_table');
```

## Documentation

Detailed guides with examples are available in the [doc/](doc/) directory:

- **[Quick Start & Basics](doc/basics.md)** — connection, select, insert, write, Statement API
- **[Async Queries](doc/async.md)** — parallel selects, batch file inserts, error handling
- **[Bindings & Conditions](doc/bindings.md)** — parameter binding, SQL templates, conditions
- **[Settings & Configuration](doc/settings.md)** — timeouts, compression, HTTPS, auth methods, sessions
- **[Streaming](doc/streaming.md)** — streamRead, streamWrite, closures, gzip
- **[Cluster](doc/cluster.md)** — multi-node setup, replicas, truncate, master node
- **[Advanced](doc/advanced.md)** — partitions, table sizes, write-to-file, progress, logging, debug

## Development

```bash
# Start ClickHouse
docker-compose -f tests/docker-compose.yaml up -d

# Run tests
./vendor/bin/phpunit

# Static analysis
./vendor/bin/phpstan analyse

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
