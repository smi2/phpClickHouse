# Structured Exceptions

The library provides detailed exception information from ClickHouse errors.

## Exception Hierarchy

```
ClickHouseException (interface)
├── QueryException (LogicException)
│   └── DatabaseException (ClickHouse server errors)
├── TransportException (RuntimeException) — curl/HTTP errors
└── ClickHouseUnavailableException — connection refused
```

## DatabaseException

Thrown when ClickHouse returns a server error (bad SQL, missing table, auth failure, etc.).

```php
use ClickHouseDB\Exception\DatabaseException;

try {
    $db->select('SELECT * FROM non_existent_table');
} catch (DatabaseException $e) {
    echo $e->getMessage();                  // "Table default.non_existent_table doesn't exist.\nIN:SELECT ..."
    echo $e->getCode();                     // 60
    echo $e->getClickHouseExceptionName();  // 'UNKNOWN_TABLE' (CH 22+) or null (older versions)
    echo $e->getQueryId();                  // 'abc-123-def' (from X-ClickHouse-Query-Id header)
}
```

### Available Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `getMessage()` | string | Error message + SQL |
| `getCode()` | int | ClickHouse error code |
| `getClickHouseExceptionName()` | ?string | e.g. `UNKNOWN_TABLE`, `SYNTAX_ERROR` (CH 22+) |
| `getQueryId()` | ?string | Query ID from response header |
| `getRequestDetails()` | array | Request metadata |
| `getResponseDetails()` | array | Response metadata |

### Common Error Codes

| Code | Exception Name | Description |
|------|---------------|-------------|
| 60 | `UNKNOWN_TABLE` | Table doesn't exist |
| 62 | `SYNTAX_ERROR` | SQL syntax error |
| 81 | `DATABASE_NOT_FOUND` | Database doesn't exist |
| 115 | `UNKNOWN_SETTING` | Invalid setting |
| 192 | `UNKNOWN_USER` | User doesn't exist |
| 241 | `MEMORY_LIMIT_EXCEEDED` | Query exceeded memory limit |
| 516 | `AUTHENTICATION_FAILED` | Wrong password or user |

### Error Format Support

The library parses both old and new ClickHouse error formats:

```
# Old format (CH < 22)
Code: 60. DB::Exception: Table default.xxx doesn't exist., e.what() = DB::Exception

# New format (CH 22+)
Code: 60. DB::Exception: Table default.xxx doesn't exist. (UNKNOWN_TABLE) (version 24.3.2.23 (official build))
```

## TransportException

Thrown for HTTP/curl-level errors (timeouts, connection refused, etc.).

```php
use ClickHouseDB\Exception\TransportException;

try {
    $db->select('SELECT sleep(100)');
} catch (TransportException $e) {
    echo $e->getMessage(); // "Operation timed out"
    echo $e->getCode();    // curl error number
}
```

## ClickHouseUnavailableException

Thrown when the server is unreachable.

```php
use ClickHouseDB\Exception\ClickHouseUnavailableException;

try {
    $db->ping(true);
} catch (ClickHouseUnavailableException $e) {
    echo "ClickHouse is down: " . $e->getMessage();
}
```
