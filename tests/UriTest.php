<?php

namespace ClickHouseDB\Tests;

use ClickHouseDB\Exception\QueryException;
use PHPUnit\Framework\TestCase;

/**
 * Class UriTest
 * @group Uri
 * @package ClickHouseDB\Tests
 */
final class UriTest extends TestCase
{
    use WithClient;

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
        $this->assertEquals('http://11.12.13.14:8123' , $cli->transport()->getUri());


        $cli->https(true);

        $this->assertEquals('https://11.12.13.14:8123' , $cli->transport()->getUri());

        $config['host']='blabla.com';
        $cli = new \ClickHouseDB\Client($config);
        $cli->https(true);

        $this->assertEquals('https://blabla.com:8123' , $cli->transport()->getUri());


        $config['host']='blabla.com:8111';
        $cli = new \ClickHouseDB\Client($config);
        $this->assertEquals('http://blabla.com:8111' , $cli->transport()->getUri());

        $config['host']='blabla.com/urls';
        $cli = new \ClickHouseDB\Client($config);
        $this->assertEquals('http://blabla.com/urls' , $cli->transport()->getUri());


        $config['host']='blabla.com';
        $config['port']=0;
        $cli = new \ClickHouseDB\Client($config);
        $this->assertEquals('http://blabla.com' , $cli->transport()->getUri());

        $config['host']='blabla.com';
        $config['port']=false;
        $cli = new \ClickHouseDB\Client($config);
        $this->assertEquals('http://blabla.com' , $cli->transport()->getUri());

        $config['host']='blabla.com:8222/path1/path';
        $config['port']=false;
        $cli = new \ClickHouseDB\Client($config);
        $this->assertEquals('http://blabla.com:8222/path1/path' , $cli->transport()->getUri());


        $config['host']='blabla.com:1234/path1/path';
        $config['port']=3344;
        $cli = new \ClickHouseDB\Client($config);
        $this->assertEquals('http://blabla.com:1234/path1/path' , $cli->transport()->getUri());


        // exit resetup
        $this->restartClickHouseClient();
    }
}
