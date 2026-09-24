# TODO: phpClickHouse Roadmap

## 1. Native Query Parameters

ClickHouse supports typed parameters over HTTP: `{name:Type}`. The server parses the values itself, so SQL injection is impossible at the protocol level.

### Current state
- `Query::isUseInUrlBindingsParams()` already detects the `{p1:UInt8}` syntax
- `Query::getUrlBindingsParams()` extracts params for the URL
- `Http::makeRequest()` passes them as `param_*` in the query string
- BUT: there is no convenient API in Client, no type validation, and no documentation

### Plan
- [ ] Add `Client::selectWithParams(string $sql, array $params, string $format = 'JSON')`
  - `$params = ['p1' => ['value' => 42, 'type' => 'UInt32']]`
  - Builds `param_p1=42` in the URL; the type is already in the SQL: `{p1:UInt32}`
- [ ] Add `Client::writeWithParams(string $sql, array $params)`: the equivalent for DDL/DML
- [ ] Validation: check that every `{name:Type}` in the SQL has a matching param
- [ ] Conversion of PHP types to CH values (DateTimeInterface → string, array → JSON, etc.)
- [ ] Don't break the existing `select()` / `write()`; the new methods live alongside them
- [ ] Tests: unit (without CH) + integration (with CH 21 and 26)
- [ ] Documentation: `doc/native-params.md`

### Files
- `src/Client.php`: new methods
- `src/Query/Query.php`: validation of params vs SQL placeholders
- `src/Query/ParamValueConverter.php`: new; converts PHP → CH string
- `tests/NativeParamsTest.php`: unit
- `tests/ClickHouse26/NativeParamsTest.php`: integration (native params are better tested on 26.x)

---

## 2. Full Support for ClickHouse Types (60+)

Extend `ValueFormatter` and add a type system for native parameters.

### Current state
- `ValueFormatter`: int, float, bool, string, null, DateTimeInterface, Expression, Type
- String types `String` (`StringType`), `FixedString(N)`, the dates `Date`, `Date32`, `DateTime`, `DateTime64`, plus `UUID`, `IPv4`, `IPv6`, `Enum8`, `Enum16` are implemented
- Strings and dates are covered by unit tests and by integration tests for CH 21 and CH 26
- The remaining type work is listed in the phases below

### Plan, Phase 1: Core types
- [ ] `src/Type/`: extend the type system:
  - [ ] `Int8`, `Int16`, `Int32`, `Int64`, `Int128`, `Int256`
  - [ ] `UInt8`, `UInt16`, `UInt32`, `UInt64` (already exists), `UInt128`, `UInt256`
  - [ ] `Float32`, `Float64`
  - [ ] `Decimal(P, S)`, `Decimal32`, `Decimal64`, `Decimal128`, `Decimal256`
  - [ ] `Bool`
- [ ] Tests for each type: insert + select + comparison

### Plan, Phase 3: Composite types
- [ ] `Array(T)`: already partially works, formalize it
- [ ] `Tuple(T1, T2, ...)`
- [ ] `Map(K, V)`
- [ ] `Nullable(T)`: already partially works
- [ ] `LowCardinality(T)`
- [ ] `Nested(name1 T1, name2 T2)`: already partial, formalize it
- [ ] Tests

### Plan, Phase 4: Specialized types
- [ ] `JSON` / `Object('json')`
- [ ] Geo: `Point`, `Ring`, `LineString`, `Polygon`, `MultiPolygon`
- [ ] `SimpleAggregateFunction`, `AggregateFunction`
- [ ] Tests

### Architecture
```
src/Type/
├── Type.php (base interface, already exists)
├── NumericType.php (already exists)
├── UInt64.php (already exists)
├── TypeRegistry.php (NEW: maps CH type name → PHP class)
├── Date32.php
├── DateTime64.php
├── IPv4.php
├── IPv6.php
├── UUIDType.php
├── MapType.php
└── TupleType.php
```

### Principles
- Every type implements the `Type` interface (`getValue()`)
- `TypeRegistry` is a singleton with mappings like `'DateTime64' → DateTime64::class`
- Backward compatibility: existing code without types keeps working
- Types are optional; raw values can still be passed as before

---

## 3. Structured Exceptions

Enrich exceptions with information from ClickHouse: error code, exception name, stack trace.

### Current state
- `DatabaseException`: parses `Code: N. DB::Exception: message`
- `TransportException`: curl errors
- `QueryException`: general query errors
- MISSING: CH exception class name, query ID, server stack trace

### Plan
- [ ] `DatabaseException`: add fields:
  - [ ] `getClickHouseExceptionName(): ?string`: `SYNTAX_ERROR`, `TABLE_NOT_FOUND`, etc.
  - [ ] `getQueryId(): ?string`: from the `X-ClickHouse-Query-Id` header
  - [ ] `getServerVersion(): ?string`: from the response
- [ ] Parse the new CH 22+ error format: `(EXCEPTION_NAME) (version X.Y.Z)`
- [ ] Extend the regex in Statement: `CLICKHOUSE_ERROR_REGEX`
- [ ] DO NOT change the `DatabaseException` constructor; add setters/a factory instead
- [ ] Tests: unit with mock responses + data provider covering different error formats
- [ ] Tests on CH 21 (old format) and CH 26 (new format)

### Files
- `src/Exception/DatabaseException.php`: extend
- `src/Statement.php`: parsing in `parseErrorClickHouse()`
- `tests/ExceptionParsingTest.php`: unit tests with data provider

---

## 4. PHPStan Level Max

Gradually raise PHPStan from level 1 to max.

### Current state
- `phpstan.neon.dist`: level 1, phpVersion 80406
- PHPStan 2.1, PHP 8.4.6
- 0 errors at level 1

### Plan: step-by-step increase
- [ ] Level 2 → fix errors → commit
- [ ] Level 3 → fix errors → commit
- [ ] Level 4 → fix errors → commit
- [ ] Level 5 → fix errors → commit (core type checks)
- [ ] Level 6 → fix errors → commit (missing typehints)
- [ ] Level 7 → fix errors → commit (union types)
- [ ] Level 8 → fix errors → commit (nullability)
- [ ] Level 9 → fix errors → commit (mixed type)
- [ ] Level max → final check

### Principles
- Each level = a separate commit
- DO NOT change public method signatures (backward compatibility!)
- Add `@phpstan-*` annotations only as a last resort
- Prefer real type fixes over suppressing errors
- Run the tests after each level

### Estimate
- Currently ~35 PHP files in src/, so the scope is manageable
- Most problems are expected at levels 5-6 (missing type hints)
- Level 8+ may require adding `@phpstan-assert` / `@phpstan-param`

---

## 5. Per-Query Settings Override

Pass ClickHouse settings at the level of an individual query.

### Current state
- `Settings` is a global object shared by all queries
- To change something you have to: `$client->settings()->set(...)` → query → `$client->settings()->set(...)` back again
- Inconvenient and not thread-safe (if the client is shared between goroutines/fibers)

### Plan
- [ ] Add a `$settings` parameter to existing methods (with default = `[]`):
  - [ ] `Client::select($sql, $bindings = [], $whereInFile = null, $writeToFile = null, array $settings = [])`
  - [ ] `Client::write($sql, $bindings = [], $exception = true, array $settings = [])`
  - [ ] `Client::selectAsync(...)`: same approach
- [ ] `Http::select()` / `Http::write()`: pass settings through into URL params
- [ ] Settings are merged: global + per-query (per-query takes priority)
- [ ] DO NOT break backward compatibility; the new parameter defaults to `[]`
- [ ] Tests: verify that per-query settings are applied and global ones are not changed

### Usage example
```php
// Global settings
$db->settings()->set('max_execution_time', 30);

// One heavy query with an increased timeout
$result = $db->select(
    'SELECT * FROM huge_table',
    [],
    null,
    null,
    ['max_execution_time' => 300, 'max_rows_to_read' => 1000000]
);

// The next query is back to 30 sec
$db->select('SELECT 1');
```

### Files
- `src/Client.php`: add the parameter
- `src/Transport/Http.php`: merge settings
- `tests/PerQuerySettingsTest.php`
- `tests/ClickHouse26/PerQuerySettingsTest.php`

---

## Priorities

| # | Task | Complexity | API breakage risk | Priority |
|---|------|------------|-------------------|----------|
| 5 | Per-query settings | Low | None | **P0** |
| 3 | Structured exceptions | Low | None | **P0** |
| 1 | Native Query Parameters | Medium | None (new methods) | **P1** |
| 4 | PHPStan level max | Medium | None | **P1** |
| 2 | 60+ types (phase 1) | Medium | None | **P2** |
| 2 | 60+ types (phases 3-4) | High | None | **P3** |

## Constraints

- **DO NOT** change signatures of existing public methods
- **DO NOT** modify existing tests
- **DO NOT** add external dependencies to `require`
- New parameters **ONLY** with default values
- Each feature = a separate branch + PR + tests for CH 21 and CH 26