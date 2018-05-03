<?php

include_once __DIR__ . '/../include.php';

$config = include_once __DIR__ . '/00_config_connect.php';


$db = new ClickHouseDB\Client($config);

//$db->verbose();
$db->settings()->readonly(false);


$result = $db->select(
    'SELECT 12 as {key} WHERE {key} = :value',
    ['key' => 'ping', 'value' => 12]
);

if ($result->fetchOne('ping') != 12) {
    echo "Error : ? \n";
}

print_r($result->fetchOne());



echo 'elapsed   :'.$result->statistics('elapsed')."\n";
echo 'rows_read :'.$result->statistics('rows_read')."\n";
echo 'bytes_read:'.$result->statistics('bytes_read')."\n";

//
$result = $db->select("SELECT 12 as ping");

print_r($result->statistics());
/*
	"statistics":
	{
		"elapsed": 0.000029702,
		"rows_read": 1,
		"bytes_read": 1
	}

 */
