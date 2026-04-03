<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Client;
use ClickHouseDB\Transport\Http;
use PHPUnit\Framework\TestCase;

/**
 * @group AuthMethodNoneTest
 */
final class AuthMethodNoneTest extends TestCase
{
    public function testAuthMethodNoneIsAccepted(): void
    {
        $config = [
            'host'        => '127.0.0.1',
            'port'        => '8123',
            'username'    => '',
            'password'    => '',
            'auth_method' => Http::AUTH_METHOD_NONE,
        ];

        $client = new Client($config);
        $this->assertInstanceOf(Client::class, $client);
    }

    public function testAuthMethodNoneConstantValue(): void
    {
        $this->assertSame(0, Http::AUTH_METHOD_NONE);
    }

    public function testAuthMethodNoneInList(): void
    {
        $this->assertContains(Http::AUTH_METHOD_NONE, Http::AUTH_METHODS_LIST);
    }
}
