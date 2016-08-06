<?php
include_once __DIR__.'/../include.php';



$config=['host'=>'x','port'=>'8123','username'=>'x','password'=>'x'];
$db=new ClickHouseDB\Client($config);

try
{
    $db->ping();
}
catch (ClickHouseDB\QueryException $E)
{
    echo "ERROR:".$E->getMessage()."\nOK\n";
}


$config=['host'=>'192.168.1.20','port'=>'8123','username'=>'x','password'=>'x'];
$db=new ClickHouseDB\Client($config);
try
{
    $db->ping();
}
catch (ClickHouseDB\QueryException $E)
{
    echo "ERROR:".$E->getMessage()."\nOK\n";
}


$config=['host'=>'192.168.1.20','port'=>'8123','username'=>'default','password'=>''];
$db=new ClickHouseDB\Client($config);
try
{
    $db->ping();
    echo "PING : OK!\n";
}
catch (ClickHouseDB\QueryException $E)
{
    echo "ERROR:".$E->getMessage()."\nOK\n";
}
try
{
    $db->select("SELECT xxx as PPPP FROM ZZZZZ ")->rows();

}
catch (ClickHouseDB\DatabaseException $E) {
    echo "ERROR : DatabaseException : ".$E->getMessage()."\n"; // Table default.ZZZZZ doesn't exist.
}

// ----------------------------