<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Statement;
use ClickHouseDB\Transport\CurlerRequest;
use ClickHouseDB\Transport\CurlerResponse;
use PHPUnit\Framework\TestCase;

/**
 * Tests for #234 — hasErrorClickhouse must not json_decode large bodies.
 *
 * @group LargeStreamTest
 * @link https://github.com/smi2/phpClickHouse/issues/234
 */
final class LargeStreamTest extends TestCase
{
    /**
     * Simulates a large JSON response body. hasErrorClickhouse should NOT
     * attempt json_decode on the entire body (would cause OOM).
     */
    public function testLargeBodyDoesNotTriggerJsonDecode(): void
    {
        // Simulate a large response (> 4096 bytes) with valid content
        $largeBody = str_repeat('{"id":1,"name":"test"}' . "\n", 500);

        $responseMock = $this->createMock(CurlerResponse::class);
        $responseMock->method('http_code')->willReturn(200);
        $responseMock->method('error_no')->willReturn(0);
        $responseMock->method('content_type')->willReturn('application/json; charset=UTF-8');
        $responseMock->method('body')->willReturn($largeBody);

        $requestMock = $this->createMock(CurlerRequest::class);
        $requestMock->method('response')->willReturn($responseMock);
        $requestMock->method('isResponseExists')->willReturn(true);

        $statement = new Statement($requestMock);

        // Should NOT throw OOM and should return false (no error)
        $this->assertFalse($statement->isError());
    }

    /**
     * Large body with ClickHouse error appended at the end (mid-stream error).
     */
    public function testLargeBodyWithErrorAtEnd(): void
    {
        $largeBody = str_repeat('{"id":1}' . "\n", 1000);
        $largeBody .= "\nCode: 241. DB::Exception: Memory limit exceeded. (MEMORY_LIMIT_EXCEEDED) (version 24.3.2.23 (official build))";

        $responseMock = $this->createMock(CurlerResponse::class);
        $responseMock->method('http_code')->willReturn(200);
        $responseMock->method('error_no')->willReturn(0);
        $responseMock->method('content_type')->willReturn('application/json; charset=UTF-8');
        $responseMock->method('body')->willReturn($largeBody);

        $requestMock = $this->createMock(CurlerRequest::class);
        $requestMock->method('response')->willReturn($responseMock);
        $requestMock->method('isResponseExists')->willReturn(true);

        $statement = new Statement($requestMock);

        $this->assertTrue($statement->isError());
    }

    /**
     * Large body with valid JSON containing ClickHouse error text as data.
     */
    public function testLargeJsonWithErrorPatternInDataIsNotError(): void
    {
        if (!function_exists('json_validate')) {
            $this->markTestSkipped('json_validate() not available');
        }

        $rows = [];
        for ($i = 0; $i < 100; $i++) {
            $rows[] = '{"id":' . $i . ',"message":"Code: 60. DB::Exception: Table default.xxx doesn\'t exist. (UNKNOWN_TABLE) (version 24.3.2.23 (official build))"}';
        }
        $body = '{"meta":[{"name":"id","type":"UInt64"},{"name":"message","type":"String"}],'
            . '"data":[' . implode(',', $rows) . '],'
            . '"rows":100,'
            . '"statistics":{"elapsed":0.001,"rows_read":100,"bytes_read":4096}}';

        // Ensure body exceeds the 4096-byte threshold
        $this->assertGreaterThan(4096, strlen($body));

        $responseMock = $this->createMock(CurlerResponse::class);
        $responseMock->method('http_code')->willReturn(200);
        $responseMock->method('error_no')->willReturn(0);
        $responseMock->method('content_type')->willReturn('application/json; charset=UTF-8');
        $responseMock->method('body')->willReturn($body);

        $requestMock = $this->createMock(CurlerRequest::class);
        $requestMock->method('response')->willReturn($responseMock);
        $requestMock->method('isResponseExists')->willReturn(true);

        $statement = new Statement($requestMock);

        $this->assertFalse($statement->isError());
    }

    /**
     * Small body with valid JSON should still be checked for JSON validity.
     */
    public function testSmallInvalidJsonDetected(): void
    {
        $responseMock = $this->createMock(CurlerResponse::class);
        $responseMock->method('http_code')->willReturn(200);
        $responseMock->method('error_no')->willReturn(0);
        $responseMock->method('content_type')->willReturn('application/json; charset=UTF-8');
        $responseMock->method('body')->willReturn('{invalid json');

        $requestMock = $this->createMock(CurlerRequest::class);
        $requestMock->method('response')->willReturn($responseMock);
        $requestMock->method('isResponseExists')->willReturn(true);

        $statement = new Statement($requestMock);

        $this->assertTrue($statement->isError());
    }
}
