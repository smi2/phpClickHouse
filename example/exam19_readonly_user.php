<?php

include_once __DIR__ . '/../include.php';

$config = include_once __DIR__ . '/00_config_connect.php';


$db = new ClickHouseDB\Client($config);


$db->enableExtremes(true)->enableHttpCompression();
$db->setReadOnlyUser(true);


// exec
$db->showDatabases();

// ----------------------------


$db = new ClickHouseDB\Client($config);

//$db->enableLogQueries()->enableHttpCompression();
//----------------------------------------
//print_r($db->select('SELECT * FROM system.query_log')->rows());

//----------------------------------------

$db->enableExtremes(true)->enableHttpCompression();



$db->showDatabases();

echo "OK?\n";
// ---------
