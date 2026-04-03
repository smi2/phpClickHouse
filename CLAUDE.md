# CLAUDE.md — phpClickHouse

## Project Overview

PHP client library for ClickHouse (`smi2/phpclickhouse`). Pure PHP, no dependencies beyond ext-curl and ext-json. Supports PHP 8.0+.

## Principles

- Follow existing patterns in surrounding code — the codebase uses Doctrine coding standard (phpcs.xml.dist)
- Write tests for new functionality — all tests require a running ClickHouse instance
- Keep changes focused — avoid over-engineering, this is a lightweight HTTP client
- Security: all user-supplied values must go through `ValueFormatter::formatValue()` or bindings (`:param`), never raw string interpolation into SQL
- Use `declare(strict_types=1)` in every PHP file
- PSR-4 autoloading: `ClickHouseDB\` namespace maps to `src/`
- **Backward compatibility is sacred:** NEVER rename, remove, or change signatures of public methods. Public API must remain fully compatible across versions. New parameters — only with default values. Deprecate, but don't break.

## Architecture

```
Client (API) → Http (transport) → CurlerRequest/CurlerRolling (curl) → Statement (results)
                ↑                        ↑
            Settings              Query + Degenerations (SQL generation)
```

- **Client.php** — user-facing API: `select()`, `write()`, `insert()`, `selectAsync()`
- **Statement.php** — result wrapper with lazy parsing, implements Iterator
- **Settings.php** — connection config (timeouts, compression, HTTPS, readonly)
- **Transport/Http.php** — HTTP layer, auth methods (header/basic/query string), URL construction
- **Transport/CurlerRolling.php** — async execution via `curl_multi_*`
- **Query/Query.php** — SQL + format management, degeneration pipeline
- **Query/Degeneration/Bindings.php** — parameter binding (`:param` → escaped value)
- **Query/Degeneration/Conditions.php** — conditional SQL template blocks
- **Quote/ValueFormatter.php** — type-aware value escaping for SQL safety
- **Cluster.php** — multi-node cluster support with health checks

## Key Notes

### Running Tests

Tests require a running ClickHouse server. Start it with Docker:

```bash
docker-compose -f tests/docker-compose.yaml up -d
```

Then run:

```bash
./vendor/bin/phpunit
```

Environment variables (defaults in `phpunit.xml.dist`):

| Variable | Default |
|---|---|
| `CLICKHOUSE_HOST` | `127.0.0.1` |
| `CLICKHOUSE_PORT` | `8123` |
| `CLICKHOUSE_USER` | `default` |
| `CLICKHOUSE_PASSWORD` | _(empty)_ |
| `CLICKHOUSE_DATABASE` | `php_clickhouse` |
| `CLICKHOUSE_TMPPATH` | `/tmp` |

**Important:** `CLICKHOUSE_DATABASE` must NOT be `default` — tests DROP and recreate the database.

### Test Setup

Tests use the `WithClient` trait (`tests/WithClient.php`). It initializes `$this->client` and `$this->tmpPath` via `@before`. The database is created once per test run (global flag hack).

### Code Quality

```bash
# Static analysis (PHPStan, level 1)
./vendor/bin/phpstan analyse

# Code style (Doctrine standard)
./vendor/bin/phpcs
```

### SQL Injection Prevention

- Never concatenate user input directly into SQL strings
- Use bindings: `$client->select('SELECT * FROM t WHERE id = :id', ['id' => $value])`
- `ValueFormatter::formatValue()` escapes strings via `addslashes()` + single-quote wrapping
- Arrays in bindings are expanded to `IN (val1, val2, ...)` lists automatically
- `Expression` and `Type` objects bypass escaping — use only for trusted values

### Auth Methods

Three methods available in `Transport/Http.php`:
- `AUTH_METHOD_HEADER` (default) — `X-ClickHouse-User` / `X-ClickHouse-Key` headers
- `AUTH_METHOD_BASIC_AUTH` — HTTP Basic Auth
- `AUTH_METHOD_QUERY_STRING` — URL parameters

### Query Formats

Supported output formats set via `Query::setFormat()`: JSON, JSONCompact, JSONEachRow, CSV, CSVWithNames, TSV, TSVWithNames, TabSeparated, and others.

### Async Queries

Async pattern uses a queue:
1. `$client->selectAsync()` — queues request (returns Statement immediately)
2. `$client->executeAsync()` — blocks until all queued requests complete
3. Access results from returned Statement objects

`CurlerRolling` limits concurrent connections to 10 by default.

### Streaming & Bulk Inserts

- `insertBatchFiles()` — async insert from CSV/TSV files
- `insertBatchStream()` — stream-based inserts
- `StreamInsert`, `StreamRead`, `StreamWrite` — gzip-capable stream wrappers

### CI

Travis CI (`.travis.yml`): tests on PHP 7.3–8.0 + nightly, with PHPStan and PHPCS on PRs.

### File Structure

```
src/
├── Client.php, Statement.php, Settings.php, Cluster.php
├── Exception/          # ClickHouseException hierarchy
├── Query/              # SQL generation, bindings, conditions
├── Quote/              # Value formatting and escaping
├── Transport/          # HTTP/curl layer
└── Type/               # Custom types (UInt64, etc.)
tests/                  # PHPUnit tests (require running ClickHouse)
example/                # 29 usage examples
```
