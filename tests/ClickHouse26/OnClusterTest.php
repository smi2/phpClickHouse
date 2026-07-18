<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\ClickHouse26;

use ClickHouseDB\Tests\WithClient;
use PHPUnit\Framework\TestCase;

/**
 * Distributed DDL (`... ON CLUSTER ...`) tests for ClickHouse 26.x.
 *
 * Regression coverage for the way the client formats distributed DDL queries
 * (see Http::prepareWrite):
 *
 *  - #241 — `CREATE USER ... ON CLUSTER` must NOT get a trailing `FORMAT JSON`
 *           appended to the SQL (ClickHouse rejects it with SYNTAX_ERROR).
 *  - #263 — `CREATE / ALTER ... ON CLUSTER` results must be parseable JSON, so
 *           reading the per-host execution status does not throw "Can`t find meta".
 *
 * The output format is requested via the `default_format=JSON` query setting
 * instead of appending `FORMAT JSON` to the SQL, which satisfies both cases.
 *
 * Requires a ClickHouse Keeper / ZooKeeper backend (see
 * tests/clickhouse-latest-config/keeper.xml). When distributed DDL is not
 * configured the whole test is skipped gracefully.
 *
 * @group ClickHouse26
 */
final class OnClusterTest extends TestCase
{
    use WithClient;

    private string $cluster = '';

    public function setUp(): void
    {
        $this->client->ping();

        $cluster = $this->client
            ->select('SELECT cluster FROM system.clusters WHERE is_local = 1 LIMIT 1')
            ->fetchOne('cluster');

        if (!is_string($cluster) || $cluster === '') {
            $this->markTestSkipped('No local cluster found in system.clusters');
        }

        // Probe distributed DDL availability: without a Keeper/ZooKeeper backend
        // ClickHouse rejects ON CLUSTER queries with NO_ELEMENTS_IN_CONFIG (code 139).
        try {
            $this->client->write(
                sprintf('DROP TABLE IF EXISTS phpunit_oncluster_probe ON CLUSTER %s', $cluster)
            );
        } catch (\Throwable $e) {
            $this->markTestSkipped('Distributed DDL is not available: ' . $e->getMessage());
        }

        $this->cluster = $cluster;
    }

    /**
     * #263 — CREATE TABLE ON CLUSTER returns a host-status result set that must be
     * parseable (no "Can`t find meta").
     */
    public function testCreateTableOnClusterReturnsReadableResult(): void
    {
        $this->client->write(sprintf('DROP TABLE IF EXISTS oncl_create ON CLUSTER %s', $this->cluster));

        $statement = $this->client->write(
            sprintf(
                'CREATE TABLE oncl_create ON CLUSTER %s (a Int32) ENGINE = MergeTree() ORDER BY a',
                $this->cluster
            )
        );

        // Must not throw — the response is JSON describing each host's status.
        $rows = $statement->rows();
        $this->assertNotEmpty($rows);
        $this->assertArrayHasKey('host', $rows[0]);
        $this->assertArrayHasKey('status', $rows[0]);

        $this->client->write(sprintf('DROP TABLE IF EXISTS oncl_create ON CLUSTER %s', $this->cluster));
    }

    /**
     * #263 — ALTER TABLE ON CLUSTER executes and returns a readable result.
     */
    public function testAlterTableOnClusterReturnsReadableResult(): void
    {
        $this->client->write(sprintf('DROP TABLE IF EXISTS oncl_alter ON CLUSTER %s', $this->cluster));
        $this->client->write(
            sprintf(
                'CREATE TABLE oncl_alter ON CLUSTER %s (a Int32, c2 Int32) ENGINE = MergeTree() ORDER BY a',
                $this->cluster
            )
        );

        $statement = $this->client->write(
            sprintf('ALTER TABLE oncl_alter ON CLUSTER %s ADD COLUMN IF NOT EXISTS c3 Int32', $this->cluster)
        );

        $rows = $statement->rows();
        $this->assertNotEmpty($rows);
        $this->assertArrayHasKey('host', $rows[0]);

        $this->client->write(sprintf('DROP TABLE IF EXISTS oncl_alter ON CLUSTER %s', $this->cluster));
    }

    /**
     * #241 — CREATE USER ON CLUSTER must not have `FORMAT JSON` appended, otherwise
     * ClickHouse fails with SYNTAX_ERROR.
     */
    public function testCreateUserOnClusterDoesNotSyntaxError(): void
    {
        try {
            $statement = $this->client->write(
                sprintf('CREATE USER OR REPLACE phpunit_oncl_user ON CLUSTER %s', $this->cluster)
            );
        } catch (\Throwable $e) {
            if (
                stripos($e->getMessage(), 'ACCESS_DENIED') !== false
                || stripos($e->getMessage(), 'Not enough privileges') !== false
            ) {
                $this->markTestSkipped('Access management is disabled: ' . $e->getMessage());
            }

            throw $e;
        }

        // No SYNTAX_ERROR from a stray FORMAT clause; the host-status result is readable.
        $this->assertNotEmpty($statement->rows());

        $this->client->write(sprintf('DROP USER IF EXISTS phpunit_oncl_user ON CLUSTER %s', $this->cluster));
    }
}
