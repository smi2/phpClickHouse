<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\ClickHouse26;

use ClickHouseDB\Tests\WithClient;
use PHPUnit\Framework\TestCase;

/**
 * @group ClickHouse26
 * @group PerQuerySettingsTest
 */
final class PerQuerySettingsTest extends TestCase
{
    use WithClient;

    public function testPerQuerySettingsAreVisibleToGetSetting(): void
    {
        $this->client->settings()->set('max_execution_time', 30);
        $globals = $this->client->settings()->getSettings();
        $sql = "SELECT getSetting('max_execution_time') AS timeout";

        $result = $this->client->select($sql, [], null, null, ['max_execution_time' => 300]);

        $this->assertSame(300, $result->fetchOne('timeout'));
        $this->assertSame($globals, $this->client->settings()->getSettings());
        $this->assertSame(30, $this->client->select($sql)->fetchOne('timeout'));
    }
}
