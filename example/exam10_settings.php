<?php

include_once __DIR__ . '/../include.php';


$config = include_once __DIR__ . '/00_config_connect.php';



$db = new ClickHouseDB\Client($config, ['max_execution_time' => 100]);

if ($db->settings()->getSetting('max_execution_time') !== 100) {
    throw new Exception("Bad work settings");
}


// set method
$config = [
    'host' => 'x',
    'port' => '8123',
    'username' => 'x',
    'password' => 'x'
];

$db = new ClickHouseDB\Client($config);
$db->settings()->set('max_execution_time', 100);

if ($db->settings()->getSetting('max_execution_time') !== 100) {
    throw new Exception("Bad work settings");
}


// apply array method
$config = [
    'host' => 'x',
    'port' => '8123',
    'username' => 'x',
    'password' => 'x'
];

$db = new ClickHouseDB\Client($config);
$db->settings()->apply([
    'max_execution_time' => 100,
    'max_block_size' => 12345
]);


if ($db->settings()->getSetting('max_execution_time') !== 100) {
    throw new Exception("Bad work settings");
}

if ($db->settings()->getSetting('max_block_size') !== 12345) {
    throw new Exception("Bad work settings");
}


echo "getSetting - OK\n";