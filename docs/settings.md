---
layout: default
title: Settings & Configuration
---

[< Back to Home](./)

# Settings & Configuration

## Setting values

Three ways to configure settings:

```php
// 1. In config array
$config = [
    'host'     => 'x',
    'port'     => '8123',
    'username' => 'x',
    'password' => 'x',
    'settings' => ['max_execution_time' => 100],
];
$db = new ClickHouseDB\Client($config);

// 2. Via constructor second argument
$db = new ClickHouseDB\Client($config, ['max_execution_time' => 100]);

// 3. Via set method
$db->settings()->set('max_execution_time', 100);

// 4. Bulk apply
$db->settings()->apply([
    'max_execution_time' => 100,
    'max_block_size'     => 12345,
]);

// Check value
$db->settings()->getSetting('max_execution_time'); // 100
```

See `example/exam10_settings.php`.

## Max execution time

```php
$db->settings()->max_execution_time(200); // seconds
```

## HTTPS

```php
$db->settings()->https();
```

### SSL CA

```php
$config = [
    'host'  => 'cluster.clickhouse.dns.com',
    'port'  => '8123',
    'sslCA' => '/path/to/ca.pem',
];
```

## Auth methods

```php
// In config
$config = [
    'host'        => 'host.com',
    'port'        => '8123',
    'username'    => 'default',
    'password'    => '',
    'auth_method' => 1, // see constants below
];
```

| Constant | Value | Description |
|----------|-------|-------------|
| `AUTH_METHOD_HEADER` | 1 | X-ClickHouse-User/Key headers (default) |
| `AUTH_METHOD_QUERY_STRING` | 2 | URL parameters |
| `AUTH_METHOD_BASIC_AUTH` | 3 | HTTP Basic Auth |

## Sessions

```php
// Create new session
$db->useSession();
$session_id = $db->getSession();

$db->write('CREATE TEMPORARY TABLE IF NOT EXISTS temp_session_test (number UInt64)');
$db->write('INSERT INTO temp_session_test SELECT number * 1234 FROM system.numbers LIMIT 30');

// Reconnect with same session
$db->useSession($session_id);
```

## Extremes

```php
$db->enableExtremes(true);
```

## HTTP compression

```php
$db->enableHttpCompression(true);
```
