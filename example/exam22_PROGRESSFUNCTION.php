<?php
include_once __DIR__ . '/../include.php';
//
$config = include_once __DIR__ . '/00_config_connect.php';


//

class progress {
    public static function printz($data)
    {
        echo "CALL CLASS:   ".json_encode($data)."\n";
    }
}

$db = new ClickHouseDB\Client($config);

// need for test
$db->settings()->set('max_block_size', 1);




// ----------------------------------------  ----------------------------------------
$db->progressFunction(function ($data) {
    echo "CALL FUNCTION:".json_encode($data)."\n";
});
$st=$db->select('SELECT number,sleep(0.2) FROM system.numbers limit 5');


// ----------------------------------------  ----------------------------------------
$db->settings()->set('http_headers_progress_interval_ms', 15); // change interval

$db->progressFunction(['progress','printz']);
$st=$db->select('SELECT number,sleep(0.1) FROM system.numbers limit 5');