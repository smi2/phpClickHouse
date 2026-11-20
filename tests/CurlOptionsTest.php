<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Client;
use ClickHouseDB\Transport\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function curl_share_init;
use function curl_share_setopt;
use function getenv;

use const CURL_LOCK_DATA_CONNECT;
use const CURLOPT_FORBID_REUSE;
use const CURLOPT_SHARE;
use const CURLSHOPT_SHARE;

#[Group('CurlOptionsTest')]
final class CurlOptionsTest extends TestCase
{
    public function testCurlOptionsPassedViaConfig(): void
    {
        $config = [
            'host'         => '127.0.0.1',
            'port'         => '8123',
            'username'     => 'default',
            'password'     => '',
            'curl_options' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ];

        $client = new Client($config);
        $this->assertInstanceOf(Client::class, $client);
    }

    public function testSetCurlOptionsOnTransport(): void
    {
        $http = new Http('127.0.0.1', 8123, 'default', '');
        $http->setCurlOptions([CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V6]);
        $this->assertInstanceOf(Http::class, $http);
    }

    public function testCurlOptionsOverrideTransportDefaults(): void
    {
        $connections = curl_share_init();
        curl_share_setopt($connections, CURLSHOPT_SHARE, CURL_LOCK_DATA_CONNECT);

        $client = new Client([
            'host'         => getenv('CLICKHOUSE_HOST'),
            'port'         => getenv('CLICKHOUSE_PORT'),
            'username'     => getenv('CLICKHOUSE_USER'),
            'password'     => getenv('CLICKHOUSE_PASSWORD'),
            'curl_options' => [
                CURLOPT_FORBID_REUSE => false,
                CURLOPT_SHARE        => $connections,
            ],
        ]);

        $first  = $client->select('SELECT 1')->responseInfo();
        $second = $client->select('SELECT 1')->responseInfo();

        $this->assertSame($first['local_port'], $second['local_port']);
    }
}
