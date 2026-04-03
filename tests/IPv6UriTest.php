<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Transport\Http;
use PHPUnit\Framework\TestCase;

/**
 * @group IPv6UriTest
 */
final class IPv6UriTest extends TestCase
{
    private function createHttp(string $host, int $port = 8123): Http
    {
        return new Http($host, $port, 'default', '');
    }

    public function testIPv6AddressWithPort(): void
    {
        $http = $this->createHttp('::1', 8123);
        $this->assertSame('http://[::1]:8123', $http->getUri());
    }

    public function testIPv6FullAddress(): void
    {
        $http = $this->createHttp('2001:db8::1', 8123);
        $this->assertSame('http://[2001:db8::1]:8123', $http->getUri());
    }

    public function testIPv6NoPort(): void
    {
        $http = $this->createHttp('::1', 0);
        $this->assertSame('http://[::1]', $http->getUri());
    }

    public function testIPv6AlreadyBracketed(): void
    {
        $http = $this->createHttp('[::1]', 8123);
        $this->assertSame('http://[::1]:8123', $http->getUri());
    }

    public function testIPv4Unchanged(): void
    {
        $http = $this->createHttp('192.168.1.1', 8123);
        $this->assertSame('http://192.168.1.1:8123', $http->getUri());
    }

    public function testHostnameWithPortUnchanged(): void
    {
        $http = $this->createHttp('clickhouse.local:9000', 8123);
        $this->assertSame('http://clickhouse.local:9000', $http->getUri());
    }

    public function testHostnameWithPathUnchanged(): void
    {
        $http = $this->createHttp('clickhouse.local/prefix', 8123);
        $this->assertSame('http://clickhouse.local/prefix', $http->getUri());
    }

    public function testLocalhostWithPort(): void
    {
        $http = $this->createHttp('localhost', 8123);
        $this->assertSame('http://localhost:8123', $http->getUri());
    }
}
