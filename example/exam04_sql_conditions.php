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

$select='SELECT  {ifint lastdays}
    
    event_date>=today()-{lastdays}
    
    {else}
    
    event_date=today()
    
    {/if}';


$statement = $db->selectAsync($select, $input_params);
echo $statement->sql();
echo "\n";



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







$select="
1: {if ll}NOT_SHOW{else}OK{/if}{if ll}NOT_SHOW{else}OK{/if}
2: {if null}NOT_SHOW{else}OK{/if} 
3: {if qwert}NOT_SHOW{/if}
4: {ifset zero} NOT_SHOW {else}OK{/if}
5: {ifset false} NOT_SHOW {/if}
6: {ifset s_false} OK {/if}
7: {ifint zero} NOT_SHOW {/if}
8: {if zero}OK{/if}
9: {ifint s_empty}NOT_SHOW{/if}
0: {ifint s_null}NOT_SHOW{/if}
1: {ifset null} NOT_SHOW {/if}


INT: 
0: {ifint zero} NOT_SHOW {/if}
1: {ifint int1} OK {/if}
2: {ifint int30} OK {/if}
3: {ifint int30}OK {else} NOT_SHOW {/if}
4: {ifint str0} NOT_SHOW {else}OK{/if}
5: {ifint str1} OK {else} NOT_SHOW {/if}
6: {ifint int30} OK {else} NOT_SHOW {/if}
7: {ifint s_empty} NOT_SHOW {else} OK {/if}
8: {ifint true} OK {else} NOT_SHOW {/if}

STRING:
0: {ifstring s_empty}NOT_SHOW{else}OK{/if}
1: {ifstring s_null}OK{else}NOT_SHOW{/if}

BOOL:
1: {ifbool int1}NOT_SHOW{else}OK{/if}
2: {ifbool int30}NOT_SHOW{else}OK{/if}
3: {ifbool zero}NOT_SHOW{else}OK{/if}
4: {ifbool false}NOT_SHOW{else}OK{/if}
5: {ifbool true}OK{else}NOT_SHOW{/if}
5: {ifbool true}OK{/if}
6: {ifbool false}OK{/if}


0: {if s_empty}


SHOW


{/if}

{ifint lastdays}


    event_date>=today()-{lastdays}-{lastdays}-{lastdays}


{else}


    event_date>=today()


{/if}
";

//
//$select='{ifint s_empty}NOT_SHOW{/if}
//1: {ifbool int1}NOT_SHOW{else}OK{/if}
//2: {ifbool int30}NOT_SHOW{else}OK{/if}
//
//';

$input_params=[
  'lastdays'=>3,
  'null'=>null,
  'false'=>false,
  'true'=>true,
  'zero'=>0,
  's_false'=>'false',
  's_null'=>'null',
  's_empty'=>'',
  'int30'=>30,
  'int1'=>1,
  'str0'=>'0',
  'str1'=>'1'
];
$statement = $db->selectAsync($select, $input_params);
echo "\n------------------------------------\n";
echo $statement->sql();
echo "\n";

/*
SELECT * FROM table
WHERE
event_date=today()
LIMIT 5
FORMAT JSON
*/