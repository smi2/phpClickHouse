<?php

include_once __DIR__.'/../include.php';

$config=['host'=>'192.168.1.20','port'=>'8123','username'=>'default','password'=>''];

$db=new ClickHouseDB\Client($config);
//$db->verbose();
$db->settings()->readonly(false);


$result=$db->select('SELECT 12 as {key} WHERE {key}=:value',['key'=>'ping','value'=>12]);

if ($result->fetchOne('ping')!=12)
{
    echo "Error : ? \n";
}
print_r($result->fetchOne());
// ---------------------------- ASYNC SELECT ----------------------------
$state1=$db->selectAsync('SELECT 1 as {key} WHERE {key}=:value',['key'=>'ping','value'=>1]);
$state2=$db->selectAsync('SELECT 2 as ping');
$db->executeAsync();
print_r($state1->fetchOne());
print_r($state1->rows());
print_r($state2->fetchOne('ping'));

//----------------------------------------//----------------------------------------

