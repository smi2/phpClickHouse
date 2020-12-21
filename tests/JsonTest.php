<?php

namespace ClickHouseDB\Tests;

use ClickHouseDB\Exception\QueryException;
use PHPUnit\Framework\TestCase;

/**
 * Class JsonTest
 * @group Json
 * @package ClickHouseDB\Tests
 */
final class JsonTest extends TestCase
{
    use WithClient;

    public function testJSONEachRow()
    {



        $state=$this->client->select('SELECT sin(number) as sin,cos(number) as cos FROM {table_name} LIMIT 2 FORMAT JSONEachRow', ['table_name'=>'system.numbers']);
        $checkString='{"sin":0,"cos":1}';
        $this->assertStringContainsString($checkString,$state->rawData());


        $state=$this->client->select('SELECT round(4+sin(number),2) as sin,round(4+cos(number),2) as cos FROM {table_name} LIMIT 2 FORMAT JSONCompact', ['table_name'=>'system.numbers']);

        $re=[
                [[4,5]],
                [[4.84,4.54]]
            ];

//        print_r($state->rows());
//        print_r($re);
//        die();
        $this->assertEquals($re,$state->rows());

    }
}
