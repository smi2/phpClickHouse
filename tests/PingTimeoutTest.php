<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Client;
use PHPUnit\Framework\TestCase;

/**
 * Standalone tests for ping() timeout behaviour that do not require a live
 * ClickHouse server.
 *
 * @group PingTimeoutTest
 */
class PingTimeoutTest extends TestCase
{
    /**
     * Verifies that setTimeout() is respected by ping().
     *
     * A local TCP server that accepts connections but never sends an HTTP
     * response is used so that the TCP handshake succeeds (preventing the
     * connect timeout from firing first) while the overall request hangs
     * until CURLOPT_TIMEOUT fires.
     */
    public function testQueryTimeoutIsRespectedByPing(): void
    {
        // Bind to an ephemeral port; the OS completes the TCP handshake for
        // queued connections, but we never call stream_socket_accept(), so
        // the HTTP response never arrives.
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($server, "Could not start local test server: $errstr");

        $address = stream_socket_get_name($server, false);
        [$host, $port] = explode(':', $address);

        $config = [
            'host'     => $host,
            'port'     => (int) $port,
            'username' => '',
            'password' => '',
        ];

        $start_time = microtime(true);

        try {
            $db = new Client($config);
            // High connect timeout so it does not fire before the query timeout.
            $db->setConnectTimeOut(5);
            $db->setTimeout(1);
            $db->ping();
        } catch (\Exception $e) {
            // Expected — ping() will throw when the query timeout fires.
        } finally {
            fclose($server);
        }

        $elapsed = round(microtime(true) - $start_time);
        $this->assertEquals(1, $elapsed);
    }
}
