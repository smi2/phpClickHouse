<?php

include_once __DIR__ . '/../include.php';

$config = include_once __DIR__ . '/00_config_connect.php';


$db = new ClickHouseDB\Client($config);

$input_params = [
    'select_date' => ['2000-10-10', '2000-10-11', '2000-10-12'],
    'limit'       => 5,
    'from_table'  => 'table'
];


$db->enableQueryConditions();


$select = '
SELECT * FROM {from_table}
WHERE
{if select_date}
event_date IN (:select_date)
{else}
event_date=today()
{/if}
{if limit}
LIMIT {limit}
{/if}
';

$statement = $db->selectAsync($select, $input_params);
echo $statement->sql();
echo "\n";

/*
SELECT * FROM table
WHERE
event_date IN ('2000-10-10','2000-10-11','2000-10-12')
LIMIT 5
FORMAT JSON
*/

$input_params['select_date'] = false;


$statement = $db->selectAsync($select, $input_params);
echo $statement->sql();
echo "\n";

/*
SELECT * FROM table
WHERE
event_date=today()
LIMIT 5
FORMAT JSON
*/