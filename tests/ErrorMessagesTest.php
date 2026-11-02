<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Client;
use ClickHouseDB\Exception\QueryException;
use ClickHouseDB\Exception\TransportException;
use ClickHouseDB\Transport\CurlerRequest;
use ClickHouseDB\Transport\CurlerRolling;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Error paths that never reach the server — no ClickHouse instance required.
 * Pins the exception types and messages so wording changes are deliberate.
 */
#[Group('ErrorMessagesTest')]
final class ErrorMessagesTest extends TestCase
{
    private const CONNECT = ['host' => '127.0.0.1', 'port' => '8123', 'username' => 'default', 'password' => ''];

    /** @return array<string, array{string, string}> */
    public static function missingConnectParams(): array
    {
        return [
            'username' => ['username', 'username is not set'],
            'password' => ['password', 'password is not set'],
            'port' => ['port', 'port is not set'],
            'host' => ['host', 'host is not set'],
        ];
    }

    #[DataProvider('missingConnectParams')]
    public function testConstructorRejectsMissingConnectParam(string $param, string $message): void
    {
        $params = self::CONNECT;
        unset($params[$param]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new Client($params);
    }

    public function testInsertBatchFilesRejectsUnsupportedFormat(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Format is not supported in insertBatchFiles');

        (new Client(self::CONNECT))->insertBatchFiles('t', [], [], 'Parquet');
    }

    public function testInsertBatchFilesRejectsMissingFile(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage("Can't read file: /nonexistent/file.csv");

        (new Client(self::CONNECT))->insertBatchFiles('t', ['/nonexistent/file.csv']);
    }

    public function testInsertBatchFilesRequiresEmptyQueue(): void
    {
        $client = new Client(self::CONNECT);
        $client->selectAsync('SELECT 1');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Queue must be empty before insertBatch; call executeAsync first');

        $client->insertBatchFiles('t', []);
    }

    public function testCurlerRollingRejectsDuplicateQueueId(): void
    {
        $rolling = new CurlerRolling();
        $rolling->addQueLoop(new CurlerRequest('same-id'));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage("Can't add existing queue item - can't overwrite : same-id!");

        $rolling->addQueLoop(new CurlerRequest('same-id'));
    }

    public function testCurlerRollingSkipsDuplicateWithoutCheck(): void
    {
        $rolling = new CurlerRolling();
        $rolling->addQueLoop(new CurlerRequest('same-id'));

        $this->assertFalse($rolling->addQueLoop(new CurlerRequest('same-id'), false));
    }
}
