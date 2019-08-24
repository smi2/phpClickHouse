<?php

namespace ClickHouseDB\Tests;

use ClickHouseDB\Exception\QueryException;
use PHPUnit\Framework\TestCase;

/**
 * Class FetchTest
 * @group FetchTest
 * @package ClickHouseDB\Tests
 */
final class FetchTest extends TestCase
{
    use WithClient;



    public function testFetchRowKeys()
    {
        $result = $this->client->select(
            'SELECT number FROM system.numbers LIMIT 5'
        );
        $this->assertEquals(null,$result->fetchRow('x'));
        $this->assertEquals(null,$result->fetchRow('y'));
        $this->assertEquals(2,$result->fetchRow('number'));
        $result->resetIterator();
        $this->assertEquals(null,$result->fetchRow('x'));
        $this->assertEquals(1,$result->fetchRow('number'));


        $this->assertEquals(null,$result->fetchOne('w'));
        $this->assertEquals(null,$result->fetchOne('q'));
        $this->assertEquals(0,$result->fetchOne('number'));
    }
    public function testFetchOne()
    {
        $result = $this->client->select(
            'SELECT number FROM system.numbers LIMIT 5'
        );
        // fetchOne
        $this->assertEquals(0,$result->fetchOne('number'));
        $this->assertEquals(0,$result->fetchOne('number'));
        $this->assertEquals(0,$result->fetchOne('number'));

        // fetchRow
        $this->assertEquals(0,$result->fetchRow('number'));
        $this->assertEquals(1,$result->fetchRow('number'));
        $this->assertEquals(2,$result->fetchRow('number'));
        $result->resetIterator();
        $this->assertEquals(0,$result->fetchRow('number'));
        $this->assertEquals(1,$result->fetchRow('number'));
        $this->assertEquals(2,$result->fetchRow('number'));
    }
}
