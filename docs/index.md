---
layout: default
title: phpClickHouse
---

# phpClickHouse

PHP client for [ClickHouse](https://clickhouse.com) — fast, lightweight, zero dependencies beyond ext-curl.

[![Downloads](https://poser.pugx.org/smi2/phpClickHouse/d/total.svg)](https://packagist.org/packages/smi2/phpClickHouse)
[![Packagist](https://poser.pugx.org/smi2/phpClickHouse/v/stable.svg)](https://packagist.org/packages/smi2/phpClickHouse)
[![Licence](https://poser.pugx.org/smi2/phpClickHouse/license.svg)](https://packagist.org/packages/smi2/phpClickHouse)

## Features

- Sync & async (parallel) SELECT queries
- [Native query parameters](native-params) — server-side `{name:Type}` binding, SQL injection impossible
- [Rich type system](types) — Boolean, Int64, Decimal, UUID, IPv4/IPv6, DateTime64, Date32, Map, Tuple
- Bulk inserts: arrays, CSV files, streams
- [Generators](generators) — memory-efficient iteration for large resultsets
- HTTP compression (gzip) for inserts
- [Parameter bindings](bindings) & SQL templates
- [Per-query settings](per-query-settings) — override ClickHouse settings per request
- [Cluster support](cluster) — auto-discovery, health checks, replicas
- [Streaming](streaming) read/write with closures
- [Progress callbacks](progress) for SELECT and INSERT
- [Structured exceptions](exceptions) — ClickHouse error name, query ID
- Sessions, write-to-file, [INSERT statistics](summary)
- HTTPS, SSL CA, IPv6 support
- Multiple auth methods (none, header, basic auth, query string)

## Installation

```bash
composer require smi2/phpclickhouse
```

Requires PHP 8.0+, ext-curl, ext-json.

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
$db->ping(true);
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
foreach ($db->selectGenerator('SELECT * FROM huge_table') as $row) {
    processRow($row);
}
```

## Documentation

### Core

| Guide | Description |
|-------|-------------|
| [Quick Start & Basics](basics) | Connection, select, insert, write, Statement API |
| [Async Queries](async) | Parallel selects, batch file inserts, error handling |
| [Bindings & Conditions](bindings) | Parameter binding, SQL templates, conditions |
| [Settings & Configuration](settings) | Timeouts, compression, HTTPS, auth methods, sessions |

### Advanced

| Guide | Description |
|-------|-------------|
| [Native Query Parameters](native-params) | Server-side `{name:Type}` binding |
| [ClickHouse Types](types) | Boolean, Int64, Decimal, UUID, IPv4/IPv6, DateTime64, Date32, Map, Tuple |
| [Generators](generators) | Memory-efficient `selectGenerator()` for large resultsets |
| [Per-Query Settings](per-query-settings) | Override settings per request |
| [Streaming](streaming) | streamRead, streamWrite, closures, gzip |
| [Cluster](cluster) | Multi-node setup, replicas, truncate, master node |

### Reference

| Guide | Description |
|-------|-------------|
| [Structured Exceptions](exceptions) | Error codes, exception names, query ID |
| [Progress Function](progress) | Real-time progress for SELECT and INSERT |
| [INSERT Statistics](summary) | written_rows, written_bytes via X-ClickHouse-Summary |
| [Advanced Features](advanced) | Partitions, table sizes, write-to-file, logging, debug |

## Version Compatibility

| PHP | phpClickHouse |
|-----|---------------|
| 5.6 | `<= 1.1.2` |
| 7.2 | `<= 1.3.10` |
| 7.3 | `1.4.x – 1.5.x` |
| 8.0+ | `1.6.0 – 1.26.4` |
| 8.1+ | `>= 1.24.406` |

## Links

- [GitHub](https://github.com/smi2/phpClickHouse)
- [Packagist](https://packagist.org/packages/smi2/phpclickhouse)
- [Changelog](https://github.com/smi2/phpClickHouse/blob/master/CHANGELOG.md)
- [habr.com — part 1](https://habrahabr.ru/company/smi2/blog/317682/)
- [habr.com — part 2](https://habr.com/company/smi2/blog/314558/)
