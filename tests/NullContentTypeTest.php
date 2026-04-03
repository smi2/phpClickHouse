<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Statement;
use ClickHouseDB\Transport\CurlerRequest;
use ClickHouseDB\Transport\CurlerResponse;
use PHPUnit\Framework\TestCase;

/**
 * @group NullContentTypeTest
 * @link https://github.com/smi2/phpClickHouse/pull/243
 */
final class NullContentTypeTest extends TestCase
{
    /**
     * isError() must not throw TypeError when content_type() returns null.
     * Curl can return null for content_type when the server closes the connection
     * before sending headers.
     */
    public function testIsErrorHandlesNullContentType(): void
    {
        $responseMock = $this->createMock(CurlerResponse::class);
        $responseMock->method('http_code')->willReturn(200);
        $responseMock->method('error_no')->willReturn(0);
        $responseMock->method('content_type')->willReturn(null);
        $responseMock->method('body')->willReturn('OK');

        $requestMock = $this->createMock(CurlerRequest::class);
        $requestMock->method('response')->willReturn($responseMock);
        $requestMock->method('isResponseExists')->willReturn(true);

        $statement = new Statement($requestMock);

        $this->assertFalse($statement->isError());
    }

    /**
     * When content_type is null and body contains a ClickHouse error,
     * isError() should still detect the error via regex fallback.
     */
    public function testIsErrorDetectsErrorWithNullContentType(): void
    {
        $errorBody = 'Code: 60. DB::Exception: Table default.xxx doesn\'t exist., e.what() = DB::Exception';

        $responseMock = $this->createMock(CurlerResponse::class);
        $responseMock->method('http_code')->willReturn(200);
        $responseMock->method('error_no')->willReturn(0);
        $responseMock->method('content_type')->willReturn(null);
        $responseMock->method('body')->willReturn($errorBody);

        $requestMock = $this->createMock(CurlerRequest::class);
        $requestMock->method('response')->willReturn($responseMock);
        $requestMock->method('isResponseExists')->willReturn(true);

        $statement = new Statement($requestMock);

        $this->assertTrue($statement->isError());
    }
}
