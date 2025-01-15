PHP ClickHouse wrapper - Changelog



======================

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
