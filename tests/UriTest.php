<?php

namespace ClickHouseDB\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Class UriTest
 * @group Uri
 * @package ClickHouseDB\Tests
 */
final class UriTest extends TestCase
{
    use WithClient;

    /**
     * @deprecated Replace with DSN support
     */
    public function testUriMake()
    {

        $config       = [
            'host'     => '11.12.13.14',
            'port'     => 8123,
            'username' => 'uu',
            'password' => 'pp',

        ];
        $cli = new \ClickHouseDB\Client($config);


        //
        $this->assertEquals('http://11.12.13.14:8123', $cli->getTransport()->getUri());


        $cli->setHttps(true);

        $this->assertEquals('https://11.12.13.14:8123', $cli->getTransport()->getUri());

        $config['host']='blabla.com';
        $cli = new \ClickHouseDB\Client($config);
        $cli->setHttps(true);

        $this->assertEquals('https://blabla.com:8123', $cli->getTransport()->getUri());


        $config['host']='blabla.com:8111';
        $cli = new \ClickHouseDB\Client($config);
        $this->assertEquals('http://blabla.com:8111', $cli->getTransport()->getUri());

        $config['host']='blabla.com/urls';
        $cli = new \ClickHouseDB\Client($config);
        $this->assertEquals('http://blabla.com/urls', $cli->getTransport()->getUri());


        $config['host']='blabla.com';
        $config['port']=0;
        $cli = new \ClickHouseDB\Client($config);
        $this->assertEquals('http://blabla.com', $cli->getTransport()->getUri());

        $config['host']='blabla.com';
        $cli = new \ClickHouseDB\Client($config);
        $this->assertEquals('http://blabla.com', $cli->getTransport()->getUri());

        $config['host']='blabla.com:8222/path1/path';
        $cli = new \ClickHouseDB\Client($config);
        $this->assertEquals('http://blabla.com:8222/path1/path', $cli->getTransport()->getUri());


        $config['host']='blabla.com:1234/path1/path';
        $config['port']=3344;
        $cli = new \ClickHouseDB\Client($config);
        $this->assertEquals('http://blabla.com:1234/path1/path', $cli->getTransport()->getUri());


        // exit resetup
        $this->restartClickHouseClient();
    }
}
