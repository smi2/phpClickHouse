<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Client;
use ClickHouseDB\Transport\Http;
use PHPUnit\Framework\TestCase;

/**
 * @group CurlOptionsTest
 */
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
}
