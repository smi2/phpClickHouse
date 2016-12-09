<?php

include_once __DIR__ . '/../include.php';

$config = [
    'host' => '192.168.1.20',
    'port' => '8123',
    'username' => 'ro',
    'password' => 'ro'
];

$db = new ClickHouseDB\Client($config);


$db->enableExtremes(true)->enableHttpCompression();
$db->setReadOnlyUser(true);


// exec
$db->showDatabases();

// ----------------------------


$config = [
    'host' => '192.168.1.20',
    'port' => '8123',
    'username' => 'ro',
    'password' => 'ro',
    'readonly' => true
];

$db = new ClickHouseDB\Client($config);

//$db->enableLogQueries()->enableHttpCompression();
//----------------------------------------
//print_r($db->select('SELECT * FROM system.query_log')->rows());

//----------------------------------------

$db->enableExtremes(true)->enableHttpCompression();



$db->showDatabases();

echo "OK?\n";
// ---------
