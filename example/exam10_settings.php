<?php

include_once __DIR__ . '/../include.php';


$config = include_once __DIR__ . '/00_config_connect.php';



$db = new ClickHouseDB\Client($config, ['readonly' => 100]);

if ($db->getSettings()->getSetting('readonly') !== 2) {
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
$db->getSettings()->set('readonly', 2);

if ($db->getSettings()->getSetting('readonly') !== 2) {
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
$db->getSettings()->apply([
    'max_block_size' => 12345
]);


if ($db->getSettings()->getSetting('readonly') !== 2) {
    throw new Exception("Bad work settings");
}

if ($db->getSettings()->getSetting('max_block_size') !== 12345) {
    throw new Exception("Bad work settings");
}


echo "getSetting - OK\n";
