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
 * Class StatementTest
 * @group StatementTest
 */
final class StatementTest extends TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testParseErrorClickHouse(
        string $errorMessage,
        string $exceptionMessage,
        int $exceptionCode
    ): void {
        $requestMock = $this->createMock(CurlerRequest::class);
        $responseMock = $this->createMock(CurlerResponse::class);

        $responseMock->expects($this->any())->method('body')->will($this->returnValue($errorMessage));
        $responseMock->expects($this->any())->method('error_no')->will($this->returnValue(0));
        $responseMock->expects($this->any())->method('error')->will($this->returnValue(false));

        $requestMock->expects($this->any())->method('response')->will($this->returnValue($responseMock));

        $statement = new Statement($requestMock);
        $this->assertInstanceOf(Statement::class, $statement);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->expectExceptionCode($exceptionCode);

        $statement->error();
    }

    /**
     * @return Generator
     */
    public function dataProvider(): Generator
    {
        yield 'Unknown setting readonly' => [
            'Code: 115. DB::Exception: Unknown setting readonly[0], e.what() = DB::Exception',
            'Unknown setting readonly[0]',
            115,
        ];

        yield 'Unknown user x' => [
            'Code: 192. DB::Exception: Unknown user x, e.what() = DB::Exception',
            'Unknown user x',
            192,
        ];

        yield 'Table default.ZZZZZ doesn\'t exist.' => [
            'Code: 60. DB::Exception: Table default.ZZZZZ doesn\'t exist., e.what() = DB::Exception',
            'Table default.ZZZZZ doesn\'t exist.',
            60,
        ];

        yield 'Authentication failed' => [
            'Code: 516. DB::Exception: test_username: Authentication failed: password is incorrect or there is no user with such name. (AUTHENTICATION_FAILED) (version 22.8.3.13 (official build))',
            'test_username: Authentication failed: password is incorrect or there is no user with such name. (AUTHENTICATION_FAILED)',
            516
        ];
    }
}
