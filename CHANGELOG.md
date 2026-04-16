PHP ClickHouse wrapper - Changelog

======================

### 2026-04-17 [Release 1.26.417]

#### Bug Fixes

* **Correctly encode arrays for native param binding** (#257) — `convertParamValue()` now produces ClickHouse-compatible array literals `['foo','bar']` (single quotes) instead of JSON format `["foo","bar"]`. Previously any `Array(String)` parameter in `selectWithParams()` / `writeWithParams()` failed with a parse error (@sander-hash)
* **Escape single quotes and backslashes in array strings** — follow-up to #257. Strings containing `'` or `\` are now properly escaped (e.g. `"it's"` → `'it\'s'`), preventing query errors and array-element injection

#### Testing

* **6 new native-params tests** — cover `Array(UInt32)`, `Array(String)`, empty arrays, strings with single quotes, strings with backslashes, and injection attempts

#### Merged PRs

* #257 — Correctly encode array for native param binding (@sander-hash)

---

### 2026-04-12 [Release 1.26.412]

#### Bug Fixes

* **Fix bindParams() TypeError with numeric keys** (#256) — `bindParams()` now casts integer array keys to string before passing to `bindParam(string $column)`, restoring backward compatibility with numerically-indexed arrays. Affected downstream packages like `phpclickhouse-laravel` (@yehezkel-fullpath)

#### Testing

* **Flaky tests excluded from default runs** — `testConnectTimeout`, `testStreamInsert`, `testStreamInsertFormatJSONEachRow` marked `@group flaky` and excluded via PHPUnit config. Run separately with `./vendor/bin/phpunit --group flaky`
* **2 new binding tests** — `testBindParamsWithNumericKeys`, `testBindParamsWithMixedKeys` covering #256 regression

#### Closed Issues

* #256 — Breaking change: strict string type on `Bindings::bindParam()` rejects integer keys

---

### 2026-04-10 [Release 1.26.410]

#### New Features

* **`readWithParams()`** (#254) — stream query results with native ClickHouse typed parameters (`{name:Type}` syntax), combining server-side parameter binding with streaming output (@sander-hash)

#### Code Quality

* **Stringable interfaces** (#252) — all Type classes now explicitly implement `Stringable` for consistency (@sander-hash)
* **PHPStan test fixes** (#253) — removed redundant assertions in static tests, improved assertion quality (@sander-hash)
* **Documentation sync** — updated GitHub Pages (docs/) with `readWithParams()` in native-params, streaming, and per-query-settings guides

#### Merged PRs

* #252 — Add Stringable interfaces for types (@sander-hash)
* #253 — Fix phpstan errors in test files (@sander-hash)
* #254 — Added native params for read function (@sander-hash)

---

### 2026-04-06 [Release 1.24.406]

#### New Features

* **Boolean Type** (#251) — new `ClickHouseDB\Type\Boolean` class with `fromString()`, `fromBool()`, `getValue()`, `__toString()` (@sander-hash)

#### Code Quality

* **PHP 8.1+ native type declarations** — full migration across all 33 src/ files: property types, parameter types, return types. Interfaces (`Degeneration`, `IStream`, `Type`) unchanged for backward compatibility
* **PR Security Review rules** — added supply-chain attack detection checklist to CLAUDE.md (outbound network calls, data exfiltration, obfuscation, credential leakage patterns)
* **Security audit** — full code audit of src/, results saved in `secure-scan.md`. All clean: no backdoors, exfiltration, or obfuscation found

#### Testing

* **153 new static unit tests** — run without ClickHouse server:
  * `ValueFormatterTest` (28) — SQL escaping, types, DateTimeInterface, Expression
  * `StrictQuoteLineTest` (32) — CSV/TSV/Insert formatting, encodeString
  * `SettingsTest` (24) — get/set/is, sessions, timeouts, readonly
  * `WriteToFileTest` (15) — file validation, formats, gzip
  * `TypesTest` (26+5) — UInt64, DateTime64, MapType, TupleType, Boolean and more
  * `BindingsUnitTest` (16) — compile_binds, Conditions {if}/{else}
  * `CurlerRequestResponseTest` (35) — headers, auth, URL, JSON, dump
* **Total: 313+ tests** (CH 21.9 + CH 26.3 + static), all passing

#### Merged PRs

* #251 — feat: Add Boolean type (@sander-hash)

---

### 2026-04-04 [Release 1.26.4]

#### New Features

**Native Query Parameters** — server-side typed parameter binding, SQL injection impossible at protocol level:
* `Client::selectWithParams()` — SELECT with `{name:Type}` placeholders
* `Client::writeWithParams()` — INSERT/DDL with `{name:Type}` placeholders
* Parameters passed as `param_*` in URL, server handles type conversion
* Supports: int, float, string, bool, null, DateTime, arrays, and all custom Type classes

**Per-Query Settings Override** — override ClickHouse settings for individual queries:
* New `$querySettings` parameter (last, default `[]`) in `select()`, `selectAsync()`, `write()`
* Also in `selectWithParams()`, `writeWithParams()`
* Per-query settings merge with global; global settings stay unchanged after query

**Generator Support** — memory-efficient iteration for large resultsets (#166):
* `Client::selectGenerator()` — streams from ClickHouse via JSONEachRow, yields one row at a time
* `Statement::rowsGenerator()` — yields rows from already-fetched data
* Supports bindings and per-query settings

**ClickHouse Type Classes** — 9 new types in `src/Type/`:
* `Int64` — large signed integers (string-based, no PHP overflow)
* `Decimal` — exact decimal numbers
* `UUID` — UUID values
* `IPv4`, `IPv6` — IP address types
* `DateTime64` — sub-second precision (`fromString()`, `fromDateTime($dt, $precision)`)
* `Date32` — extended date range (1900–2299)
* `MapType` — `Map(K, V)` composite type
* `TupleType` — `Tuple(T1, T2, ...)` composite type
* All types work with `insert()`, bindings (`:param`), and native parameters (`{name:Type}`)

**Structured Exceptions** — enriched error information from ClickHouse:
* `DatabaseException::getClickHouseExceptionName()` — e.g. `UNKNOWN_TABLE`, `SYNTAX_ERROR` (CH 22+)
* `DatabaseException::getQueryId()` — from `X-ClickHouse-Query-Id` response header
* `DatabaseException::fromClickHouse()` — factory method
* Parses both old (`e.what() = DB::Exception`) and new (`(EXCEPTION_NAME) (version ...)`) error formats

**INSERT Statistics via X-ClickHouse-Summary** (#233):
* `Statement::summary()` — reads `X-ClickHouse-Summary` response header (written_rows, written_bytes, etc.)
* `Statement::statistics()` — falls back to summary for INSERT queries (was always null before)

**IPv6 Support** — `getUri()` correctly wraps bare IPv6 addresses in brackets

**AUTH_METHOD_NONE** (0) — skip authentication for trusted/proxy setups

**Custom curl Options** — pass arbitrary `CURLOPT_*` via config `curl_options` or `Http::setCurlOptions()`

**GitHub Actions CI** — replaces Travis CI (#176):
* Matrix: PHP 8.0, 8.1, 8.2, 8.3, 8.4
* Two ClickHouse versions: 21.9 + 26.3.3.20
* PHPStan and PHPCS jobs

#### Bug Fixes

* **Fix streaming OOM** (#234) — `hasErrorClickhouse()` no longer calls `json_decode()` on large response bodies. Bodies > 4KB: only tail checked for error patterns. Prevents OOM when using `streamRead()` with large JSON resultsets
* **Fix progressFunction for write/insert** (#191) — added `wait_end_of_query=1` setting, required for ClickHouse to send progress headers during write operations
* **Fix null content_type** (#243) — `hasErrorClickhouse()` handles null content_type from curl without TypeError
* **Fix FORMAT JSON in DDL** (#242) — FORMAT JSON no longer appended to CREATE, DROP, ALTER, RENAME statements
* **Fix URL bindings with large inserts** (#240) — `isUseInUrlBindingsParams()` checks original SQL before degeneration, preventing false matches from data containing `{foo:bar}` patterns
* **Fix ping() timeout** (#246) — `ping()` now respects `setTimeout()` value (was ignoring CURLOPT_TIMEOUT)
* **Remove deprecated curl_close()** (#244) — no-op since PHP 8.0, deprecated in PHP 8.5
* **Fix PHPStan errors** — `$params` undefined in `Query::getUrlBindingsParams()`, missing return in `CurlerResponse::dump()`
* **Fix docblock** (#247) — `$bind` param type `string[]` → `array<string, mixed>` in `streamRead()`/`streamWrite()`

#### Code Quality

* **PHPStan level 1 → 5** — with baseline for 60 pre-existing errors in curl layer. All new code must pass level 5
* **PHPStan upgraded** from 0.12 to 2.1 (supports PHP 8.4)
* **phpVersion: 80406** set in PHPStan config

#### Testing

* **Two ClickHouse test targets**: 21.9 (port 8123) + 26.3.3.20 (port 8124)
* **Two PHPUnit configs**: `phpunit-ch21.xml` (original tests) + `phpunit-ch26.xml` (adapted for CH 26 behavioral changes)
* **CH 26 adapted tests** in `tests/ClickHouse26/`: ClientTest, SessionsTest, StatementTest, UInt64Test
* **New test files**: NativeParamsTest, PerQuerySettingsTest, StructuredExceptionTest, GeneratorTest, LargeStreamTest, TypesTest, SummaryTest, NullContentTypeTest, IPv6UriTest, AuthMethodNoneTest, CurlOptionsTest, ProgressWriteTest, PingTimeoutTest, QueryTest
* **160 tests (CH 21) + 146 tests (CH 26)** — all passing

#### Documentation

* **README.md** restructured — concise overview with links to doc/
* **doc/** — 13 documentation files:
  * `basics.md` — connection, select, insert, Statement API
  * `async.md` — parallel queries, batch inserts
  * `bindings.md` — parameter binding, SQL templates
  * `settings.md` — timeouts, HTTPS, auth, sessions
  * `streaming.md` — streamRead/Write, closures, gzip
  * `cluster.md` — multi-node setup, replicas
  * `advanced.md` — partitions, table sizes, progress, debug
  * `types.md` — all 9 type classes with examples
  * `native-params.md` — server-side `{name:Type}` parameters
  * `per-query-settings.md` — per-query settings override
  * `generators.md` — selectGenerator(), rowsGenerator()
  * `progress.md` — progressFunction for SELECT and INSERT
  * `exceptions.md` — structured exceptions
  * `summary.md` — INSERT statistics via X-ClickHouse-Summary
* **CLAUDE.md** — project guidelines, architecture, contribution rules
* **todo.md** — development roadmap

#### Merged PRs

* #246 — fix: respect custom query timeout in ping() method (@abodnar)
* #244 — remove deprecated curl_close() call (@yehezkel-fullpath)
* #243 — fix: handle null content_type in hasErrorClickhouse() (@nzsakib)
* #242 — fix: remove FORMAT JSON from DDL statements (@jspeedz)
* #240 — fix: bindings in URL with large insert queries (@sniek-ie)
* #238 — fix: metadata parsing for distributed RENAME queries (@iTearo)
* #236 — support Enums in ValueFormatter.php (@wlkns)

#### Closed Issues

#234, #233, #227, #225, #223, #215, #209, #208, #201, #197, #196, #195, #194, #193, #191, #181, #176, #166, #150, #144, #136

---

### 2025-01-14 [Release 1.6.0]
* Support PHP 8.4

### 2024-01-18 [Release 1.5.3]
* Fix release 1.5.2
* Support php 7
* Update Statement.php #204
* fix(#202): Fix converting boolean when inserting into int and fix(#194): Fix unexpected readonly mode with specific string in query #203
* Update README.md #199
* remove dev files for --prefer-dist #192



### 2024-01-16 [Release 1.5.2]
* Update Statement.php #204
* fix(#202): Fix converting boolean when inserting into int and fix(#194): Fix unexpected readonly mode with specific string in query #203
* Update README.md #199
* remove dev files for --prefer-dist #192

### May 25, 2023 [ 1.5.1 ]  
* BREAKING CHANGES Post type bindings support

### 2022-12-20  [Release 1.5.0]

* Change exceptionCode in Clickhouse version 22.8.3.13 (official build) #180 
* Fix Docker for tests, Change the correct Docker image name #177
* Some type fix
* Fix types: max_execution_time & setConnectTimeOut, undo: Support floats in timeout and connect_timeout #173
* add mbstring to composer require #183
* fixed progressFunction #182
* Add allow_plugins setting since Composer 2.2.x #178


### 2022-04-23  [Release 1.4.4]
* Fix ping() for windows users 
* ping(true) throw TransportException if can`t connect/ping

### 2022-04-20  [Release 1.4.3]
* Fix: prevent enable_http_compression parameter from being overridden #164
* For correct work with utf-8 . I am working on server with PHP 5.6.40 Update CurlerRequest.php #158
* Add curl setStdErrOut, for custom StdOutErr. 
* Fix some test for check exceptions

### 2022-02-11  [Release 1.4.2]
*  Fixed issue with non-empty raw data processing during init() on every fetchRow() and fetchOne() call - PR #161

### 2021-01-19 [Release 1.4.1]
* Add support php 7.3 & php 8


### 2019-09-29 [Release 1.3.10]
* Add two new types of authentication #139
* Fixed typo in streamRead exception text #140
* fix the exception(multi-statement not allow) when sql end with ';' #138
* Added more debug info for empty response with error #135



### 2020-02-03 [Release 1.3.9]
* #134 Enhancement: Add a new exception to be able to distinguish that ClickHouse is not available. 


### 2020-01-17 [Release 1.3.8]
* #131 Fix: async loop breaks after 20 seconds 
* #129 Add client certificate support to able to work with Yandex ClickHouse cloud hosting 
* Delete `dropOldPartitions`
* Fix error : The error of array saving #127
* More test 

### 2019-09-20 [Release 1.3.7]
* #125 WriteToFile: support for JSONEachRow format

### 2019-08-24 [Release 1.3.6]
* #122 Add function fetchRow()
* Use X-ClickHouse-User by headers
* Add setDirtyCurler() in HTTP
* Add more tests
      


### 2019-04-29 [Release 1.3.5]
* Reupload 1.3.4

### 2019-04-29 [Release 1.3.4]
* #118 Fix Error in Conditions & more ConditionsTest
* Fix phpStan warnings in getConnectTimeOut() & max_execution_time()



### 2019-04-25 [Release 1.3.3]
* fix clickhouse release 19.5.2.6 error parsing #117 
* chore(Travis CI): Enable PHP 7.3 testing #116 


### 2019-03-18 [Release 1.3.2]
* fix: add CSVWithNames to supported formats #107
* Upgraded Expression proposal #106 -> UUIDStringToNum
* Correct query format parsing #108
* Can not use numbers() function in read requests #110
* #109 Do not use keep-alive or reuse them across requests

### 2018-09-25 [Release 1.3.1]
* Pull request #94 from simPod: Uint64 values
* Pull request #95 from simPod: Bump to php 7.1

### 2018-09-11 [Release 1.2.4]
* Fix #91 ,Does not work inserting with the database name in the table
* pull request #90 from simPod: Refactor partitions()

### 2018-08-30 [Release 1.2.3]
* Escape values in arrays, pull request #87 from simPod/fix-escape
* fix-bindings: pull request #84 from simPod/fix-bindings
* Added quotes arount table and column names in the insert wrapper.
* Docker Compose in tests


### 2018-07-24 [Release 1.2.2]
* Connection without [port](https://github.com/smi2/phpClickHouse#connection-without-port) 


### 2018-07-16 [Release 1.2.1]
* New `$client->getServerVersion()`
* Rewrite method `$client->ping()`
* Fix `include.php` - ClickHouseException before exceptions
* Add CHANGELOG.md
* New `interface ClickHouseException`

### 2018-07-06 [Release 1.2.0]
* Fix `SelectAsync() & executeAsync()`, some task freeze

### 2018-07-04 [Release 1.1.2]
* Republic 1.1.1

### 2018-07-02 [Release 1.1.1]
* #47 Bindings wrong work - fix


### 2018-07-02 [Release 1.1.0]


New:
* `$client->getServerUptime()` Returns the server's uptime in seconds.
* `$client->getServerSystemSettings()` Read system.settings table and return array
* `$client->streamWrite()` function
* `$client->streamRead()` function


Warning:
* Now default enable`HttpCompression` set true
* Deprecated `StreamInsert` class

Fix:
* Fix `rawData()` result in `JSONCompact & JSONEachRow` format
* Fix Statement - unnecessary memory usage
* Fix support php5.6



### 2018-06-29 [Release 1.0.1]
* Do not convert int parameters in array to string in Bindings [pull 67](https://github.com/smi2/phpClickHouse/pull/67)
*

### 2018-06-25 [Release 1.0.0]
* Use Semantic versioning


### 2018-06-22

* Fix `tableSize('name')` and `tablesSize()`



### 2018-06-19
* Add DataTime Interface for Bind
* Fix phpDoc
* `Composer->require->"php": ">=5.6"`


### 2018-05-09
* Move `\ClickHouseDB\WhereInFile` to `\ClickHouseDB\Query\WhereInFile`
* Move `\ClickHouseDB\QueryException` to `\ClickHouseDB\Exception\QueryException`
* Move `\ClickHouseDB\DatabaseException` to `ClickHouseDB\Exception\DatabaseException`
* Move `\ClickHouseDB\FormatLine` to `\ClickHouseDB\Quote\FormatLine`
* Move `\ClickHouseDB\WriteToFile` to `ClickHouseDB\Query\WriteToFile`
* Move `\Curler\Request` to `\ClickHouseDB\Transport\CurlerRequest`
* Move `\Curler\CurlerRolling` to `\ClickHouseDB\Transport\CurlerRolling`
* Up to php 7.2 & phpunit 7.1 for Dev & Prs4 Autoloading



### 2018-03-26

* Fix StreamInsert : one stream work faster and safe than loop #PR43
* Fix cluster->clientLike()

### 2017-12-28

* Fix `FORMAT JSON` if set FORMAT in sql
* GetRaw() - result raw response if not json ``SELECT number as format_id FROM system.numbers LIMIT 3 FORMAT CSVWithNames``

### 2017-12-22

* progressFunction()
* Escape values

### 2017-12-12

* Not set `FORMAT JSON` if set FORMAT in sql

### 2017-11-22

- Add insertAssocBulk

### 2017-08-25

- Fix tablesSize(), use database filter
- Fix partitions(), use database filter

### 2017-08-14

- Add session_id support

### 2017-02-20

- Build composer 0.17.02

### 2016-12-09

- for ReadOnly users need set : `client->setReadOnlyUser(true);` or `$confi['readonly']` , see exam19_readonly_user.php

###  2016-11-25

- `client->truncateTable('tableName')`
- `cluster->getMasterNodeForTable('dbName.tableName') // node have is_leader=1`
- `cluster->getSizeTable('dbName.tableName')`
- `cluster->getTables()`
- `cluster->truncateTable('dbName.tableName')`
- See example cluster_06_truncate_table.php

###  2016-11-24

- add `cluster->setSoftCheck()`
- insertBatchFiles() support `$file_names` - string or array , `$columns_array` - array or null
- add insertBatchStream() return `\Curler\Request` no exec
- writeStreamData() return `\Curler\Request`
- fix httpCompression(false)
- getHeaders() as array from `\Curler\Request`
- `setReadFunction( function() )` in `Request`
- Add class StreamInsert, direct read from stream_resource to clickhouse:stream

###  2016-11-04

- add `$db->insertBatchTSVFiles()`,
- add format param in `$db->insertBatchFiles(,,,format)`,
- deprecated class CSV
- Add static class `\ClickHouseDB\FormatLine:CSV(),\ClickHouseDB\FormatLine:TSV(),\ClickHouseDB\FormatLine:Insert()`
- CSV RFC4180 - `\ClickHouseDB\FormatLine::CSV(Array))."\n"`
- Update exam12_array.php + unit tests

###  2016-11-03

- `$db->enableLogQueries(true)` - write to system.query_log
- `$db->enableExtremes(true);` - default extremes now, disabled
- `$db->isExists($database,$table)`

###  2016-10-27

- add Connect timeout , $db->setConnectTimeOut(5);
- change default ConnectTimeOut = 5 seconds. before 1 sec.
- change DNS_CACHE default to 120 seconds

###  2016-10-25 Release 0.16.10

- fix timeout error and add test

###  2016-10-23

- client->setTimeout($seconds)
- cluster->clientLike($cluster,$ip_addr_like)
- Delete all migration code from driver, move to https://github.com/smi2/phpMigrationsClickhouse

###  2016-09-20 Release 0.16.09

- Version/Release names: [ zero dot year dot month]
- Support cluster: new class Cluster and ClusterQuery
- output_format_write_statistics, for clickhouse version > v1.1.54019-stable
- WriteToFile in select,selectAsync
- Degeneration for Bindings & Conditions
- $db->select(new Query("Select..."));
- remove findActiveHostAndCheckCluster , clusterHosts , checkServerReplicas
- Add cleanQueryDegeneration(),addQueryDegeneration()
- Need $db->enableQueryConditions(); for use Conditions ; default Conditions - disabled;
- float in CurlerRequest->timeOut(2.5) = 2500 ms
- tablesSize() - add `sizebytes`


### 2016-08-11 Release 0.2.0

- exception on error write

### 2016-08-06 Release 0.1.0

- init
