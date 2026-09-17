<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Exception\DatabaseException;
use ClickHouseDB\Statement;
use ClickHouseDB\Transport\CurlerRequest;
use ClickHouseDB\Transport\CurlerResponse;
use Generator;
use PHPUnit\Framework\TestCase;

final class ExceptionParsingTest extends TestCase
{
    /** @dataProvider errorFormatProvider */
    public function testParsesResponseMetadata(
        string $body,
        int $code,
        ?string $name,
        ?string $version,
        ?string $trace,
        ?string $queryId
    ): void {
        $response = $this->createMock(CurlerResponse::class);
        $response->method('body')->willReturn($body);
        // Streaming errors can arrive after the server has already sent HTTP 200.
        $response->method('http_code')->willReturn(200);
        $response->method('content_type')->willReturn('text/plain');
        $response->method('error_no')->willReturn(0);
        $response->method('error')->willReturn('');
        $response->method('headers')->with('X-ClickHouse-Query-Id')->willReturn($queryId);
        $request = $this->createMock(CurlerRequest::class);
        $request->method('response')->willReturn($response);
        $request->method('getRequestExtendedInfo')->willReturnMap([['sql', 'SELECT broken']]);

        try {
            (new Statement($request))->error();
            self::fail('Expected DatabaseException');
        } catch (DatabaseException $exception) {
            self::assertSame($code, $exception->getCode());
            self::assertSame($name, $exception->getClickHouseExceptionName());
            self::assertSame($queryId, $exception->getQueryId());
            self::assertSame($version, $exception->getServerVersion());
            self::assertSame($trace, $exception->getServerStackTrace());
            self::assertStringContainsString('broken', $exception->getMessage());
            self::assertStringEndsWith("\nIN:SELECT broken", $exception->getMessage());
        }
    }

    public function errorFormatProvider(): Generator
    {
        yield 'legacy e.what without metadata' => [
            'Code: 60. DB::Exception: Table broken does not exist., e.what() = DB::Exception',
            60, null, null, null, null,
        ];
        yield 'legacy with version' => [
            'Code: 62. DB::Exception: Syntax error: broken (version 21.3.20.1 (official build))',
            62, null, '21.3.20.1', null, 'old-query-id',
        ];
        yield 'modern syntax error' => [
            'Code: 62. DB::Exception: Syntax error: broken. (SYNTAX_ERROR) (version 26.3.3.20 (official build))',
            62, 'SYNTAX_ERROR', '26.3.3.20', null, 'new-query-id',
        ];
        yield 'multiline message and plain version' => [
            "Code: 60. DB::Exception: Table broken\ndoes not exist. (UNKNOWN_TABLE) (version 26.3.3)",
            60, 'UNKNOWN_TABLE', '26.3.3', null, null,
        ];
        yield 'name without version' => [
            'Code: 117. DB::Exception: broken UUID. (CANNOT_PARSE_UUID)',
            117, 'CANNOT_PARSE_UUID', null, null, null,
        ];
        yield 'bare error' => ['Code: 42. DB::Exception: broken', 42, null, null, null, null];
        yield 'parentheses inside message are not an exception name' => [
            'Code: 62. DB::Exception: broken (SELECT) near input. (version 21.9.6.24 (official build))',
            62, null, '21.9.6.24', null, null,
        ];
        foreach (['21.9.6.24', '26.3.3.20'] as $version) {
            yield 'stack trace ' . $version => [
                'Code: 46. DB::Exception: Unknown function broken. (UNKNOWN_FUNCTION), '
                    . "Stack trace (when copying this message, always include the lines below):\n\n"
                    . "0. DB::Exception::Exception() @ 0x123\n1. DB::executeQuery() @ 0x456\n"
                    . ' (version ' . $version . " (official build))\n",
                46, 'UNKNOWN_FUNCTION', $version,
                "0. DB::Exception::Exception() @ 0x123\n1. DB::executeQuery() @ 0x456", 'trace-query-id',
            ];
        }
        yield 'legacy stack trace without version' => [
            "Code: 60. DB::Exception: broken, e.what() = DB::Exception, Stack trace:\n\n0. DB::executeQuery() @ 0x123\n",
            60, null, null, '0. DB::executeQuery() @ 0x123', null,
        ];
        yield 'streamed error after data' => [
            '{"data":[{"value":1}Code: 241. DB::Exception: broken. (MEMORY_LIMIT_EXCEEDED)'
                . ' (version 26.3.3.20 (official build))',
            241, 'MEMORY_LIMIT_EXCEEDED', '26.3.3.20', null, null,
        ];
    }

    public function testConstructorAndExistingFactoryRemainCompatible(): void
    {
        $previous = new \RuntimeException('previous');
        $exception = new DatabaseException('message', 42, $previous);
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame('message', $exception->getMessage());
        self::assertSame(42, $exception->getCode());
        self::assertNull($exception->getClickHouseExceptionName());
        self::assertNull($exception->getQueryId());
        self::assertNull($exception->getServerVersion());
        self::assertNull($exception->getServerStackTrace());

        $exception = DatabaseException::fromClickHouse('message', 42, 'SOME_ERROR', 'query-id');
        self::assertSame('SOME_ERROR', $exception->getClickHouseExceptionName());
        self::assertSame('query-id', $exception->getQueryId());
        self::assertNull($exception->getServerVersion());
        self::assertNull($exception->getServerStackTrace());
    }
}
