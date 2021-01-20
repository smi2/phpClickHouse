<?php

namespace ClickHouseDB\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Class ProgressAndEscapeTest
 * @group ProgressAndEscapeTest
 * @package ClickHouseDB\Tests
 */
final class ProgressAndEscapeTest extends TestCase
{
    use WithClient;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        date_default_timezone_set('Europe/Moscow');

        $this->client->ping();
    }

    public function testProgressFunction()
    {
        global $resultTest;

        $this->client->settings()->set('max_block_size', 1);
        $this->client->progressFunction(function ($data) {
            global $resultTest;
            $resultTest=$data;
        });
        $st=$this->client->select('SELECT number,sleep(0.1) FROM system.numbers limit 2 UNION ALL SELECT number,sleep(0.1) FROM system.numbers limit 12');

        // read_rows + read_bytes + total_rows
        $this->assertArrayHasKey('read_rows',$resultTest);
        $this->assertArrayHasKey('read_bytes',$resultTest);
        $this->assertArrayHasKey('written_rows',$resultTest);
        $this->assertArrayHasKey('written_bytes',$resultTest);

        if (isset($resultTest['total_rows']))
        {
            $this->assertArrayHasKey('total_rows',$resultTest);
        } else {
            $this->assertArrayHasKey('total_rows_to_read',$resultTest);
        }
        // Disable test GreaterThan - travis-ci is fast
        // $this->assertGreaterThan(1,$resultTest['read_rows']);
        // $this->assertGreaterThan(1,$resultTest['read_bytes']);
    }
}
