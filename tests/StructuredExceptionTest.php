<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Exception\DatabaseException;
use ClickHouseDB\Statement;
use ClickHouseDB\Transport\CurlerRequest;
use ClickHouseDB\Transport\CurlerResponse;
use Generator;
use PHPUnit\Framework\TestCase;

/**
 * @group StructuredExceptionTest
 */
final class StructuredExceptionTest extends TestCase
{
    use WithClient;

    /**
     * @dataProvider exceptionNameProvider
     */
    public function testExceptionNameParsing(string $errorBody, int $expectedCode, ?string $expectedName): void
    {
        $responseMock = $this->createMock(CurlerResponse::class);
        $responseMock->method('body')->willReturn($errorBody);
        $responseMock->method('error_no')->willReturn(0);
        $responseMock->method('error')->willReturn(false);
        $responseMock->method('headers')->willReturn(null);

        $requestMock = $this->createMock(CurlerRequest::class);
        $requestMock->method('response')->willReturn($responseMock);

        $statement = new Statement($requestMock);

        try {
            $statement->error();
            $this->fail('Expected DatabaseException');
        } catch (DatabaseException $e) {
            $this->assertEquals($expectedCode, $e->getCode());
            $this->assertEquals($expectedName, $e->getClickHouseExceptionName());
        }
    }

    public function exceptionNameProvider(): Generator
    {
        yield 'CH 22+ format with exception name' => [
            'Code: 60. DB::Exception: Table default.xxx doesn\'t exist. (UNKNOWN_TABLE) (version 24.3.2.23 (official build))',
            60,
            'UNKNOWN_TABLE',
        ];

        yield 'CH 22+ syntax error' => [
            'Code: 62. DB::Exception: Syntax error: SELECT GARBAGE. (SYNTAX_ERROR) (version 24.3.2.23 (official build))',
            62,
            'SYNTAX_ERROR',
        ];

        yield 'CH 22+ auth failed' => [
            'Code: 516. DB::Exception: user: Authentication failed: password is incorrect. (AUTHENTICATION_FAILED) (version 24.3.2.23 (official build))',
            516,
            'AUTHENTICATION_FAILED',
        ];

        yield 'Old CH format without exception name' => [
            'Code: 60. DB::Exception: Table default.xxx doesn\'t exist., e.what() = DB::Exception',
            60,
            null,
        ];

        yield 'Old CH format unknown setting' => [
            'Code: 115. DB::Exception: Unknown setting readonly[0], e.what() = DB::Exception',
            115,
            null,
        ];
    }

    public function testQueryIdFromHeader(): void
    {
        $responseMock = $this->createMock(CurlerResponse::class);
        $responseMock->method('body')->willReturn(
            'Code: 60. DB::Exception: Table default.xxx doesn\'t exist. (UNKNOWN_TABLE) (version 24.3.2.23 (official build))'
        );
        $responseMock->method('error_no')->willReturn(0);
        $responseMock->method('error')->willReturn(false);
        $responseMock->method('headers')->willReturnCallback(function ($name) {
            if ($name === 'X-ClickHouse-Query-Id') {
                return 'abc-123-def';
            }
            return null;
        });

        $requestMock = $this->createMock(CurlerRequest::class);
        $requestMock->method('response')->willReturn($responseMock);

        $statement = new Statement($requestMock);

        try {
            $statement->error();
            $this->fail('Expected DatabaseException');
        } catch (DatabaseException $e) {
            $this->assertEquals('abc-123-def', $e->getQueryId());
            $this->assertEquals('UNKNOWN_TABLE', $e->getClickHouseExceptionName());
        }
    }

    public function testDatabaseExceptionFromClickHouseFactory(): void
    {
        $e = DatabaseException::fromClickHouse('Some error', 42, 'SOME_ERROR', 'query-123');
        $this->assertEquals('Some error', $e->getMessage());
        $this->assertEquals(42, $e->getCode());
        $this->assertEquals('SOME_ERROR', $e->getClickHouseExceptionName());
        $this->assertEquals('query-123', $e->getQueryId());
    }

    public function testLiveExceptionHasStructuredData(): void
    {
        try {
            $this->client->select('SELECT * FROM non_existent_table_xyz_123')->rows();
            $this->fail('Expected exception');
        } catch (DatabaseException $e) {
            $this->assertGreaterThan(0, $e->getCode());
        } catch (\ClickHouseDB\Exception\QueryException $e) {
            // QueryException is also acceptable (wraps DatabaseException)
            $this->assertGreaterThan(0, $e->getCode());
        }
    }
}
