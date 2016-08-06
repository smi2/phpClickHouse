<?php
include_once __DIR__.'/../include.php';
$config=['host'=>'192.168.1.20','port'=>'8123','username'=>'default','password'=>''];
$db=new ClickHouseDB\Client($config);


$db->write("DROP TABLE IF EXISTS arrays_test");
$res=$db->write('
CREATE TABLE IF NOT EXISTS arrays_test
(
    s_key String,
    s_arr Array(UInt8)
) ENGINE = Memory
');
//------------------------------------------------------------------------------
echo "Insert\n";
$stat=$db->insert('arrays_test',
    [
        ['HASH1',[11,22,33]],
        ['HASH1',[11,22,55]],
    ]
    ,
    ['s_key','s_arr']
);
echo "Insert Done\n";

print_r($db->select('SELECT s_key, s_arr FROM arrays_test ARRAY JOIN s_arr')->rows());

$db->write("DROP TABLE IF EXISTS arrays_test_string");
$res=$db->write('
CREATE TABLE IF NOT EXISTS arrays_test_string
(
    s_key String,
    s_arr Array(String)
) ENGINE = Memory
');
echo "Insert\n";
$stat=$db->insert('arrays_test_string',
    [
        ['HASH1',["a","dddd","xxx"]],
        ['HASH1',["b'\tx"]],
    ]
    ,
    ['s_key','s_arr']
);
echo "Insert Done\n";


print_r($db->select('SELECT s_key, s_arr FROM arrays_test_string ARRAY JOIN s_arr')->rows());


