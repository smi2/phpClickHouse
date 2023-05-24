<?php

namespace ClickHouseDB\Tests;
use PHPUnit\Framework\TestCase;

/**
 * @group BindingsPost
 */
final class BindingsPostTest extends TestCase
{
    use WithClient;


    public function testSelectPostParams()
    {
        $xpx1=time();
        $result = $this->client->select(
            'SELECT number+{num_num:UInt8} as numbe_r, {xpx1:UInt32} as xpx1,{zoza:String} as zoza FROM system.numbers LIMIT 6',
            [
                'num_num'=>123,
                'xpx1'=>$xpx1,
                'zoza'=>'ziza'
            ]
        );
        $this->assertEquals(null,$result->fetchRow('x'));   //0
        $this->assertEquals(null,$result->fetchRow('y'));   //1
        $this->assertEquals($xpx1,$result->fetchRow('xpx1'));        //2
        $this->assertEquals('ziza',$result->fetchRow('zoza'));//3
        $this->assertEquals(127,$result->fetchRow('numbe_r')); // 123+4
        $this->assertEquals(128,$result->fetchRow('numbe_r')); // 123+5 item
    }

    public function testSelectAsKeys()
    {
        // chr(0....255);
        $this->client->settings()->set('max_block_size', 100);

        $bind['k1']=1;
        $bind['k2']=2;

        $select=[];
        for($z=0;$z<4;$z++)
        {
            $bind['k'.$z]=$z;
            $select[]="{k{$z}:UInt16} as k{$z}";
        }
        $rows=$this->client->select("SELECT ".implode(",\n",$select),$bind)->rows();

        $this->assertNotEmpty($rows);

        $row=$rows[0];

        for($z=10;$z<4;$z++) {
            $this->assertArrayHasKey('k'.$z,$row);
            $this->assertEquals($z,$row['k'.$z]);

        }
    }

    public function testArrayAsPostParam()
    {
        $arr = [1,3,6];
        $result = $this->client->select(
            'SELECT {arr:Array(UInt8)} as arr',
            [
                'arr'=>json_encode($arr)
            ]
        );
        $this->assertEquals($arr, $result->fetchRow('arr'));
    }

}
