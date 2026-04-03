<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Statement;
use ClickHouseDB\Transport\CurlerRequest;
use ClickHouseDB\Transport\CurlerResponse;
use PHPUnit\Framework\TestCase;

/**
 * @group SummaryTest
 * @link https://github.com/smi2/phpClickHouse/issues/233
 */
final class SummaryTest extends TestCase
{
    use WithClient;

    /**
     * INSERT should return written_rows via summary().
     */
    public function testSummaryAfterInsert(): void
    {
        $this->client->write('
            CREATE TABLE IF NOT EXISTS test_summary (
                id UInt32,
                name String
            ) ENGINE = Memory
        ');

        $stat = $this->client->insert('test_summary',
            [
                [1, 'a'],
                [2, 'b'],
                [3, 'c'],
            ],
            ['id', 'name']
        );

        $summary = $stat->summary();

        // X-ClickHouse-Summary may not be present on older ClickHouse versions
        if ($summary !== null) {
            $this->assertIsArray($summary);
            $this->assertArrayHasKey('written_rows', $summary);
            $this->assertNotNull($stat->summary('written_rows'));
        } else {
            $this->markTestSkipped('X-ClickHouse-Summary header not present (older ClickHouse version)');
        }
    }

    /**
     * statistics() should fallback to summary for INSERT queries.
     */
    public function testStatisticsFallbackToSummary(): void
    {
        $this->client->write('
            CREATE TABLE IF NOT EXISTS test_summary_fallback (
                id UInt32
            ) ENGINE = Memory
        ');

        $stat = $this->client->insert('test_summary_fallback',
            [[1], [2]],
            ['id']
        );

        $statistics = $stat->statistics();

        if ($statistics !== null) {
            $this->assertIsArray($statistics);
            $this->assertArrayHasKey('written_rows', $statistics);
        } else {
            $this->markTestSkipped('X-ClickHouse-Summary header not present (older ClickHouse version)');
        }
    }

    /**
     * statistics() with key should fallback to summary for INSERT queries.
     */
    public function testStatisticsWithKeyFallbackToSummary(): void
    {
        $this->client->write('
            CREATE TABLE IF NOT EXISTS test_summary_key (
                id UInt32
            ) ENGINE = Memory
        ');

        $stat = $this->client->insert('test_summary_key',
            [[1], [2], [3], [4], [5]],
            ['id']
        );

        $writtenRows = $stat->statistics('written_rows');

        if ($writtenRows !== null) {
            $this->assertNotNull($writtenRows);
        } else {
            $this->markTestSkipped('X-ClickHouse-Summary header not present (older ClickHouse version)');
        }
    }

    /**
     * summary() returns null when header is absent (unit test with mock).
     */
    public function testSummaryReturnsNullWhenNoHeader(): void
    {
        $responseMock = $this->createMock(CurlerResponse::class);
        $responseMock->method('http_code')->willReturn(200);
        $responseMock->method('error_no')->willReturn(0);
        $responseMock->method('content_type')->willReturn(null);
        $responseMock->method('body')->willReturn('Ok.');
        $responseMock->method('headers')->willReturn(null);

        $requestMock = $this->createMock(CurlerRequest::class);
        $requestMock->method('response')->willReturn($responseMock);
        $requestMock->method('isResponseExists')->willReturn(true);

        $statement = new Statement($requestMock);

        $this->assertNull($statement->summary());
        $this->assertNull($statement->summary('written_rows'));
    }

    /**
     * SELECT should still return statistics from body, not header.
     */
    public function testSelectStatisticsStillWork(): void
    {
        $this->client->settings()->set('output_format_write_statistics', true);

        $stat = $this->client->select('SELECT 1 as n');
        $statistics = $stat->statistics();

        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('elapsed', $statistics);
    }
}
