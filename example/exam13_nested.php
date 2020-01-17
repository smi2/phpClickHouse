<?php

include_once __DIR__ . '/../include.php';

$config = include_once __DIR__ . '/00_config_connect.php';


$db = new ClickHouseDB\Client($config);


$db->write("DROP TABLE IF EXISTS NestedNested_test");

$res = $db->write('
    CREATE TABLE IF NOT EXISTS NestedNested_test (
        s_key String,
        topics Nested( id UInt8 , ww Float32 ),
        s_arr Array(String)
    ) ENGINE = Memory
');

//------------------------------------------------------------------------------

$XXX=['AAA'."'".'A',"BBBBB".'\\'];

print_r($XXX);

echo "Insert\n";
$stat = $db->insert('NestedNested_test', [
    ['HASH\1', [11,33],[3.2,2.1],$XXX],
], ['s_key', 'topics.id','topics.ww','s_arr']);
echo "Insert Done\n";

print_r($db->select('SELECT * FROM NestedNested_test')->rows());
print_r($db->select('SELECT * FROM NestedNested_test ARRAY JOIN topics')->rows());

