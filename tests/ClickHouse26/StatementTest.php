<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\ClickHouse26;

use ClickHouseDB\Exception\DatabaseException;
use ClickHouseDB\Statement;
use ClickHouseDB\Transport\CurlerRequest;
use ClickHouseDB\Transport\CurlerResponse;
use ClickHouseDB\Tests\WithClient;
use Generator;
use PHPUnit\Framework\TestCase;

/**
 * StatementTest adapted for ClickHouse 26.x
 * CH 26.x returns HTTP 500 for mid-stream errors (unlike 200 in CH 21.x).
 *
 * @group ClickHouse26
 */
final class StatementTest extends TestCase
{
    use WithClient;

    public function testIsError(): void
    {
        $result = $this->client->select(
            'SELECT throwIf(1=1, \'Raised error\');'
        );

        $this->assertGreaterThanOrEqual(500, $result->getRequest()->response()->http_code());
        $this->assertTrue($result->isError());
    }

    /**
     * In CH 26.x, mid-stream errors return HTTP 500 instead of 200.
     */
    public function testIsErrorWithMidStreamError(): void
    {
        $result = $this->client->select(
            'SELECT number, throwIf(number=100100, \'Raised error\') FROM system.numbers;'
        );

        // CH 26.x returns 500 for mid-stream errors
        $this->assertEquals(500, $result->getRequest()->response()->http_code());
        $this->assertTrue($result->isError());
    }

    /**
     * @link https://github.com/smi2/phpClickHouse/issues/223
     */
    public function testIsNotErrorWhenJsonBodyContainsDbExceptionMessage(): void
    {
        $result = $this->client->select(
            "SELECT
                    'mutation_123456' AS mutation_id,
                    'Code: 243. DB::Exception: Cannot reserve 61.64 GiB, not enough space. (NOT_ENOUGH_SPACE) (version 24.3.2.23 (official build))' AS latest_fail_reason"
        );

        $this->assertEquals(200, $result->getRequest()->response()->http_code());
        $this->assertFalse($result->isError());
    }

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

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->expectExceptionCode($exceptionCode);

        $statement->error();
    }

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
