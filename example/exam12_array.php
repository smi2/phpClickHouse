<?php

include_once __DIR__ . '/../include.php';

$config = include_once __DIR__ . '/00_config_connect.php';


$db = new ClickHouseDB\Client($config);


$db->write("DROP TABLE IF EXISTS arrays_test");

$res = $db->write('
    CREATE TABLE IF NOT EXISTS arrays_test (
        s_key String,
        s_arr Array(UInt8)
    ) ENGINE = Memory
');

//------------------------------------------------------------------------------


echo "Insert\n";
$stat = $db->insert('arrays_test', [
    ['HASH1', [11, 22, 33]],
    ['HASH1', [11, 22, 55]],
], ['s_key', 's_arr']);
echo "Insert Done\n";

print_r($db->select('SELECT s_key, s_arr FROM arrays_test ARRAY JOIN s_arr')->rows());

$db->write("DROP TABLE IF EXISTS arrays_test_string");

$res = $db->write('
    CREATE TABLE IF NOT EXISTS arrays_test_string (
        s_key String,
        s_arr Array(String)
    ) ENGINE = Memory
');


echo "Insert\n";
$stat = $db->insert('arrays_test_string', [
    ['HASH1', ["a", "dddd", "xxx"]],
    ['HASH1', ["b'\tx"]],
], ['s_key', 's_arr']);
echo "Insert Done\n";


print_r($db->select('SELECT s_key, s_arr FROM arrays_test_string ARRAY JOIN s_arr')->rows());


echo "\ntestRFCCSVWrite>>>>\n";
$fileName='/tmp/testRFCCSVWrite.CSV';
date_default_timezone_set('Europe/Moscow');
$db->write("DROP TABLE IF EXISTS testRFCCSVWrite");
$db->write('CREATE TABLE testRFCCSVWrite (
           event_date Date DEFAULT toDate(event_time),
           event_time DateTime,
           strs String,
           flos Float32,
           ints Int32,
           arr1 Array(UInt8),
           arrs Array(String)
        ) ENGINE = TinyLog()');

@unlink($fileName);

$data=[
    ['event_time'=>date('Y-m-d H:i:s'),'strs'=>'SOME STRING','flos'=>1.1,'ints'=>1,'arr1'=>[1,2,3],'arrs'=>["A","B"]],
    ['event_time'=>date('Y-m-d H:i:s'),'strs'=>'SOME STRING','flos'=>2.3,'ints'=>2,'arr1'=>[1,2,3],'arrs'=>["A","B"]],
    ['event_time'=>date('Y-m-d H:i:s'),'strs'=>'SOME\'STRING','flos'=>0,'ints'=>0,'arr1'=>[1,2,3],'arrs'=>["A","B"]],
    ['event_time'=>date('Y-m-d H:i:s'),'strs'=>'SOME\'"TRING','flos'=>0,'ints'=>0,'arr1'=>[1,2,3],'arrs'=>["A","B"]],
    ['event_time'=>date('Y-m-d H:i:s'),'strs'=>"SOMET\nRI\n\"N\"G\\XX_ABCDEFG",'flos'=>0,'ints'=>0,'arr1'=>[1,2,3],'arrs'=>["A","B\nD\nC"]],
    ['event_time'=>date('Y-m-d H:i:s'),'strs'=>"ID_ARRAY",'flos'=>0,'ints'=>0,'arr1'=>[1,2,3],'arrs'=>["A","B\nD\nC"]]
];

//// 1.1 + 2.3 = 3.3999999761581
//
foreach ($data as $row)
{
    file_put_contents($fileName,\ClickHouseDB\Quote\FormatLine::CSV($row)."\n",FILE_APPEND);
}
//
echo "FILE:\n\n";
echo file_get_contents($fileName)."\n\n----\n";

//
$db->insertBatchFiles('testRFCCSVWrite', [$fileName], [
    'event_time',
    'strs',
    'flos',
    'ints',
    'arr1',
    'arrs',
]);

$st=$db->select('SELECT * FROM testRFCCSVWrite');
print_r($st->rows());
//


echo "\n<<<<< TAB >>>>\n";
$fileName='/tmp/testRFCCSVWrite.TAB';@unlink($fileName);


$db->write("DROP TABLE IF EXISTS testTABWrite");
$db->write('CREATE TABLE testTABWrite (
           event_date Date DEFAULT toDate(event_time),
           event_time DateTime,
           strs String,
           flos Float32,
           ints Int32,
           arr1 Array(UInt8),
           arrs Array(String)
        ) ENGINE = Log()');



$data=[
    ['event_time'=>date('Y-m-d H:i:s'),'strs'=>"STING\t\tSD!\"\nFCD\tSAD\t\nDSF",'flos'=>-2.3,'ints'=>123,'arr1'=>[1,2,3],'arrs'=>["A","B"]],
    ['event_time'=>date('Y-m-d H:i:s'),'strs'=>'SOME\'STRING','flos'=>0,'ints'=>12123,'arr1'=>[1,2,3],'arrs'=>["A","B"]],
    ['event_time'=>date('Y-m-d H:i:s'),'strs'=>'SOME\'"TR\tING','flos'=>0,'ints'=>0,'arr1'=>[1,2,3],'arrs'=>["A","B"]],
    ['event_time'=>date('Y-m-d H:i:s'),'strs'=>"SOMET\nRI\n\"N\"G\\XX_ABCDEFG",'flos'=>0,'ints'=>1,'arr1'=>[1,2,3],'arrs'=>["A","B\nD\ns\tC"]],
    ['event_time'=>date('Y-m-d H:i:s'),'strs'=>"ID_ARRAY",'flos'=>-2.3,'ints'=>-12123,'arr1'=>[1,2,3],'arrs'=>["A","B\nD\nC\n\t\n\tTABARRAYS"]]
];


foreach ($data as $row)
{
    file_put_contents($fileName,\ClickHouseDB\Quote\FormatLine::TSV($row)."\n",FILE_APPEND);
}
//
echo "FILE:\n\n";
echo file_get_contents($fileName)."\n\n----\n";

//
$db->insertBatchTSVFiles('testTABWrite', [$fileName], [
    'event_time',
    'strs',
    'flos',
    'ints',
    'arr1',
    'arrs',
]);

$st=$db->select('SELECT * FROM testTABWrite');
print_r($st->rows());
$st=$db->select('SELECT round(sum(flos),5),sum(ints) FROM testTABWrite');
print_r($st->rows());

//
$db->write("DROP TABLE IF EXISTS NestedNested_arr");

$res = $db->write('
    CREATE TABLE IF NOT EXISTS NestedNested_arr (
        s_key String,
        s_arr Array(String)
    ) ENGINE = Memory
');

//------------------------------------------------------------------------------

$XXX=['AAA'."'".'A',"BBBBB".'\\'];

print_r($XXX);

echo "Insert\n";
$stat = $db->insert('NestedNested_arr', [
    ['HASH\1', $XXX],
], ['s_key','s_arr']);
echo "Insert Done\n";

print_r($db->select('SELECT * FROM NestedNested_arr WHERE s_key=\'HASH\1\'')->rows());
