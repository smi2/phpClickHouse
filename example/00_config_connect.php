<?php
return [
    'host' => getenv('CLICKHOUSE_HOST'),
    'port' => '8123',
    'username' => 'default',
    'password' => '',
    'auth_method' => 1, // On of HTTP::AUTH_METHODS_LIST
];