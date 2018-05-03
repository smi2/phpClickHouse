<?php

include_once __DIR__ . '/../include.php';


$config = include_once __DIR__ . '/00_config_connect.php';


$db = new ClickHouseDB\Client($config);

try {
    $db->ping();
}
catch (ClickHouseDB\Exception\QueryException $E) {
    echo "ERROR:" . $E->getMessage() . "\nOK\n";
}


// ------------------


$db = new ClickHouseDB\Client([
    'host' => 'NO_DB_HOST.COM',
    'port' => '8123',
    'username' => 'x',
    'password' => 'x'
]);
$db->setConnectTimeOut(1);
try {
    $db->ping();
}
catch (ClickHouseDB\Exception\QueryException $E) {
    echo "ERROR:" . $E->getMessage() . "\nOK\n";
}


// ------------------



$db = new ClickHouseDB\Client($config);

try {
    $db->ping();
    echo "PING : OK!\n";
}
catch (ClickHouseDB\Exception\QueryException $E) {
    echo "ERROR:" . $E->getMessage() . "\nOK\n";
}

try {
    $db->select("SELECT xxx as PPPP FROM ZZZZZ ")->rows();
}
catch (ClickHouseDB\Exception\DatabaseException $E) {
    echo "ERROR : DatabaseException : " . $E->getMessage() . "\n"; // Table default.ZZZZZ doesn't exist.
}

// ----------------------------