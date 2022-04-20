<?php

namespace ClickHouseDB\Tests;

use ClickHouseDB\Client;
use ClickHouseDB\Transport;
use PHPUnit\Framework\TestCase;

/**
 * Class ClientAuthTest
 * @package ClickHouseDB\Tests
 * @group ClientAuthTest
 */
class ClientAuthTest extends TestCase
{


    public function setUp(): void
    {
        date_default_timezone_set('Europe/Moscow');
    }

    /**
     *
     */
    public function tearDown(): void
    {
        //
    }

    private function getConfig():array
    {
       return [
            'host'     => getenv('CLICKHOUSE_HOST'),
            'port'     => getenv('CLICKHOUSE_PORT'),
            'username' => getenv('CLICKHOUSE_USER'),
            'password' => getenv('CLICKHOUSE_PASSWORD'),

        ];
    }

    private function execCommand(array $config):string
    {
        $cli = new Client($config);
        $cli->verbose();
        $stream = fopen('php://memory', 'r+');
        // set stream to curl
        $cli->transport()->setStdErrOut($stream);
        // exec
        $st=$cli->select('SElect 1 as ppp');
        $st->rows();
        fseek($stream,0,SEEK_SET);
        return stream_get_contents($stream);
    }

    public function testInsertDotTable()
    {
        $conf=$this->getConfig();


        // AUTH_METHOD_BASIC_AUTH
        $conf['auth_method']=Transport\Http::AUTH_METHOD_BASIC_AUTH;

        $data=$this->execCommand($conf);
        $this->assertIsString($data);
        $this->assertStringContainsString('Authorization: Basic ZGVmYXVsdDo=',$data);
        $this->assertStringNotContainsString('&user=default&password=',$data);
        $this->assertStringNotContainsString('X-ClickHouse-User',$data);

        // AUTH_METHOD_QUERY_STRING
        $conf['auth_method']=Transport\Http::AUTH_METHOD_QUERY_STRING;

        $data=$this->execCommand($conf);
        $this->assertIsString($data);
        $this->assertStringContainsString('&user=default&password=',$data);
        $this->assertStringNotContainsString('Authorization: Basic ZGVmYXVsdDo=',$data);
        $this->assertStringNotContainsString('X-ClickHouse-User',$data);


        // AUTH_METHOD_HEADER
        $conf['auth_method']=Transport\Http::AUTH_METHOD_HEADER;

        $data=$this->execCommand($conf);
        $this->assertIsString($data);
        $this->assertStringNotContainsString('&user=default&password=',$data);
        $this->assertStringNotContainsString('Authorization: Basic ZGVmYXVsdDo=',$data);
        $this->assertStringContainsString('X-ClickHouse-User',$data);

    }


}
