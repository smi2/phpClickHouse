<?php
include_once __DIR__ . '/../include.php';
$config = include_once __DIR__ . '/00_config_connect.php';



$db = new ClickHouseDB\Client($config);
$db->verbose();

// ---------------------------------------- NO HTTPS ----------------------------------------
$db->select('SELECT 11');



// ---------------------------------------- ADD HTTPS ----------------------------------------
$db->setHttps(true);

$db->select('SELECT 11');




// --------------------- $db->setHtps(); --------------------------------

$db = new ClickHouseDB\Client($config);
$db->verbose();
$db->setHttps(true);
$db->select('SELECT 11');
