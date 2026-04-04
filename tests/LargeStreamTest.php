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
