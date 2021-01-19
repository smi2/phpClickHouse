<?php

namespace ClickHouseDB\Tests;
use PHPUnit\Framework\TestCase;

/**
 * @group ConditionsTest
 */
final class ConditionsTest extends TestCase
{
    use WithClient;

    private function getInputParams()
    {
        return [
            'topSites'=>30,
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
    }

    private function condTest($sql,$equal)
    {
        $equal=$equal.' FORMAT JSON';
        $input_params=$this->getInputParams();
//        echo "-----\n".$this->client->selectAsync($sql, $input_params)->sql()."\n----\n";
        
        $this->assertEquals($equal,$this->client->selectAsync($sql, $input_params)->sql());


    }
    /**
     *
     */
    public function testSqlConditionsBig()
    {


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
            
            
            CHECK_INT: 
            0: {ifint zero} NOT_SHOW {/if}
            1: {ifint int1} OK {/if}
            2: {ifint int30} OK {/if}
            3: {ifint int30}OK {else} NOT_SHOW {/if}
            4: {ifint str0} NOT_SHOW {else}OK{/if}
            5: {ifint str1} OK_11 {else} NOT_SHOW {/if}
            6: {ifint int30} OK_22 {else} NOT_SHOW {/if}
            7: {ifint s_empty} NOT_SHOW {else} OK {/if}
            8: {ifint true} OK_33 {else} NOT_SHOW {/if}
            
            CHECK_STRING:
            0: {ifstring s_empty}NOT_SHOW{else}OK{/if}
            1: {ifstring s_null}OK{else}NOT_SHOW{/if}
            LAST_LINE_1
            BOOL:
            1: {ifbool int1}NOT_SHOW{else}OK{/if}
            2: {ifbool int30}NOT_SHOW{else}OK_B11{/if}
            3: {ifbool zero}NOT_SHOW{else}OK_B22{/if}
            4: {ifbool false}NOT_SHOW{else}OK{/if}
            5: {ifbool true}OK{else}NOT_SHOW{/if}
            5: {ifbool true}OK{/if}
            6: {ifbool false}OK{/if}
            0: s_empty_check:{if s_empty}
            
            SHOW
            
            {/if}
            CHECL_IFINT:
            {ifint lastdays}
            
            
                event_date>=today()-{lastdays}
            
            
            {else}
            
            
                event_date>=today()
           
            
            {/if} LAST_LINE_2
            {ifint lastdays}
        event_date>=today()-{lastdays}
      {else}
        event_date>=today()
      {/if}
            {ifset topSites}
             AND  site_id in ( {->Sites->Top(topSites)} )
            {/if}
            {ifset topSites}
             AND  site_id in ( {->Sites->Top(topSites)} )
            {/if}
            

             {if topSites}
                    AND  site_id in ( {->Sites->Top(topSites)} )
             {/if}
              

        ";

        $this->restartClickHouseClient();
        $this->client->enableQueryConditions();
        $input_params=$this->getInputParams();

        $result=$this->client->selectAsync($select, $input_params)->sql();

        $this->assertStringNotContainsString('NOT_SHOW',$result);
        $this->assertStringContainsString('s_empty_check',$result);
        $this->assertStringContainsString('LAST_LINE_1',$result);
        $this->assertStringContainsString('LAST_LINE_2',$result);
        $this->assertStringContainsString('CHECL_IFINT',$result);
        $this->assertStringContainsString('CHECK_INT',$result);
        $this->assertStringContainsString('CHECK_STRING',$result);
        $this->assertStringContainsString('OK_11',$result);
        $this->assertStringContainsString('OK_22',$result);
        $this->assertStringContainsString('OK_33',$result);
        $this->assertStringContainsString('OK_B11',$result);
        $this->assertStringContainsString('OK_B22',$result);
        $this->assertStringContainsString('=today()-3',$result);

//        echo "\n----\n$result\n----\n";

    }
    public function testSqlConditions1()
    {
        $this->restartClickHouseClient();
        $this->client->enableQueryConditions();

        $this->condTest('{ifint s_empty}NOT_SHOW{/if}{ifbool int1}NOT_SHOW{else}OK{/if}{ifbool int30}NOT_SHOW{else}OK{/if}','OKOK');
        $this->condTest('{ifbool false}OK{/if}{ifbool true}OK{/if}{ifbool true}OK{else}NOT_SHOW{/if}','OKOK');
        $this->condTest('{ifstring s_empty}NOT_SHOW{else}OK{/if}{ifstring s_null}OK{else}NOT_SHOW{/if}','OKOK');
        $this->condTest('{ifint int1} OK {/if}',' OK');
        $this->condTest('{ifint s_empty}NOT_SHOW{/if}_1_','_1_');
        $this->condTest('1_{ifint str0} NOT_SHOW {else}OK{/if}_2','1_OK_2');
        $this->condTest('1_{if zero}OK{/if}_2','1_OK_2');
        $this->condTest('1_{if empty}OK{/if}_2','1__2');
        $this->condTest('1_{if s_false}OK{/if}_2','1_OK_2');
        $this->condTest('1_{if qwert}NOT_SHOW{/if}_2','1__2');
        $this->condTest('1_{ifset zero} NOT_SHOW {else}OK{/if}{ifset false} NOT_SHOW {/if}{ifset s_false} OK {/if}_2','1_OK OK_2');
        $this->condTest('1_{ifint zero} NOT_SHOW {/if}{if zero}OK{/if}{ifint s_empty}NOT_SHOW{/if}_2','1_OK_2');
        $this->condTest('1_{ifint s_null}NOT_SHOW{/if}{ifset null} NOT_SHOW {/if}_2','1__2');
        $this->condTest("{ifint lastdays}\n\n\nevent_date>=today()-{lastdays}-{lastdays}-{lastdays}\n\n\n{else}\n\n\nevent_date>=today()\n\n\n{/if}", "\n\n\nevent_date>=today()-3-3-3\n\n\n");
        $this->condTest("1_{ifint lastdays}\n2_{lastdays}_\t{int1}_{str0}_{str1}\n_6{else}\n\n{/if}", "1_\n2_3_\t1_0_1\n_6");
        $this->condTest("1_{ifint qwer}\n\n\n\n_6{else}\n{int1}{str0}{str1}\n{/if}\n_77", "1_\n101\n_77");


    }
    public function testSqlConditions()
    {
        $input_params = [
            'select_date' => ['2000-10-10', '2000-10-11', '2000-10-12'],
            'limit'       => 5,
            'from_table'  => 'table_x_y',
            'idid'        => 0,
            'false'       => false
        ];

        $this->assertEquals(
            'SELECT * FROM table_x_y FORMAT JSON',
            $this->client->selectAsync('SELECT * FROM {from_table}', $input_params)->sql()
        );

        $this->assertEquals(
            'SELECT * FROM table_x_y WHERE event_date IN (\'2000-10-10\',\'2000-10-11\',\'2000-10-12\') FORMAT JSON',
            $this->client->selectAsync('SELECT * FROM {from_table} WHERE event_date IN (:select_date)', $input_params)->sql()
        );

        $this->client->enableQueryConditions();

        $this->assertEquals(
            'SELECT * FROM ZZZ LIMIT 5 FORMAT JSON',
            $this->client->selectAsync('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if}', $input_params)->sql()
        );

        $this->assertEquals(
            'SELECT * FROM ZZZ NOOPE FORMAT JSON',
            $this->client->selectAsync('SELECT * FROM ZZZ {if nope}LIMIT {limit}{else}NOOPE{/if}', $input_params)->sql()
        );
        $this->assertEquals(
            'SELECT * FROM 0 FORMAT JSON',
            $this->client->selectAsync('SELECT * FROM :idid', $input_params)->sql()
        );


        $this->assertEquals(
            'SELECT * FROM  FORMAT JSON',
            $this->client->selectAsync('SELECT * FROM :false', $input_params)->sql()
        );



        $isset=[
            'FALSE'=>false,
            'ZERO'=>0,
            'NULL'=>null

        ];

        $this->assertEquals(
            '|ZERO|| FORMAT JSON',
            $this->client->selectAsync('{if FALSE}FALSE{/if}|{if ZERO}ZERO{/if}|{if NULL}NULL{/if}| ' ,$isset)->sql()
        );



    }


    public function testSqlDisableConditions()
    {
        $this->assertEquals('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if} FORMAT JSON',  $this->client->selectAsync('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if}', [])->sql());
        $this->assertEquals('SELECT * FROM ZZZ {if limit}LIMIT 123{/if} FORMAT JSON',  $this->client->selectAsync('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if}', ['limit'=>123])->sql());
        $this->client->cleanQueryDegeneration();
        $this->assertEquals('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if} FORMAT JSON',  $this->client->selectAsync('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if}', ['limit'=>123])->sql());
        $this->restartClickHouseClient();
        $this->assertEquals('SELECT * FROM ZZZ {if limit}LIMIT 123{/if} FORMAT JSON',  $this->client->selectAsync('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if}', ['limit'=>123])->sql());


    }
}
