<?php

namespace ClickHouseDB\Tests;

use ClickHouseDB\Exception\QueryException;
use PHPUnit\Framework\TestCase;

/**
 * Class StreamTest
 * @group Stream
 * @package ClickHouseDB\Tests
 */
final class StreamTest extends TestCase
{
    use WithClient;

    public function testStreamRead()
    {


        $stream = fopen('php://memory','r+');
        $streamRead=new \ClickHouseDB\Transport\StreamRead($stream);
        $callable = function ($ch, $string) use ($stream) {
            // some magic for _BLOCK_ data
            fwrite($stream, str_ireplace('"sin"','"max"',$string));
            return strlen($string);
        };

        $streamRead->closure($callable);

        $state=$this->client->streamRead($streamRead,'SELECT sin(number) as sin,cos(number) as cos FROM {table_name} LIMIT 2 FORMAT JSONEachRow', ['table_name'=>'system.numbers']);
        rewind($stream);
        $bufferCheck='';
        while (($buffer = fgets($stream, 4096)) !== false) {
            $bufferCheck=$bufferCheck.$buffer;
        }
        fclose($stream);

        $checkString='{"max":0,"cos":1}';

        $this->assertStringContainsString($checkString,$bufferCheck);

    }
    public function testStreamInsert()
    {

        $this->client->write('DROP TABLE IF EXISTS _phpCh_SteamTest');
        $this->client->write('CREATE TABLE _phpCh_SteamTest (a Int32) Engine=Log');


        $stream = fopen('php://memory','r+');
        for($f=0;$f<121123;$f++)
            fwrite($stream, json_encode(['a'=>$f]).PHP_EOL );
        rewind($stream);

        $streamWrite=new \ClickHouseDB\Transport\StreamWrite($stream);

        $streamWrite->applyGzip();

        $callable = function ($ch, $fd, $length) use ($stream) {
            return ($line = fread($stream, $length)) ? $line : '';
        };


        $streamWrite->closure($callable);

        $state=$this->client->streamWrite($streamWrite,'INSERT INTO {table_name} FORMAT JSONEachRow', ['table_name'=>'_phpCh_SteamTest']);
        $sum=$this->client->select("SELECT sum(a) as s FROM _phpCh_SteamTest ")->fetchOne('s');
        $this->assertEquals(7335330003, $sum);

    }
}
