<?php

include_once __DIR__ . '/../include.php';

$config = [
    'host' => '192.168.1.20',
    'port' => '8123',
    'username' => 'default',
    'password' => ''
];

$db = new ClickHouseDB\Client($config);

$db->enableLogQueries()->enableHttpCompression();
//----------------------------------------
print_r($db->select('SELECT * FROM system.query_log')->rows());
