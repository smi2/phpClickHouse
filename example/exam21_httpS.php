<?php
include_once __DIR__ . '/../include.php';
$config = include_once __DIR__ . '/00_config_connect.php';



$db = new ClickHouseDB\Client($config);
$db->verbose();

// ---------------------------------------- NO HTTPS ----------------------------------------
$db->select('SELECT 11');



// ---------------------------------------- ADD HTTPS ----------------------------------------
$db->https();

$db->select('SELECT 11');




// --------------------- $db->settings()->https(); --------------------------------

$db = new ClickHouseDB\Client($config);
$db->verbose();
$db->settings()->https();
$db->select('SELECT 11');




// --------------------- $config['https']=true; --------------------------------

$config['https']=true;

$db = new ClickHouseDB\Client($config);
$db->verbose();
$db->select('SELECT 11');
