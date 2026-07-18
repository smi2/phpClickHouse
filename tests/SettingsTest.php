<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $settings = new Settings();

        self::assertFalse($settings->get('extremes'));
        self::assertTrue($settings->get('readonly'));
        self::assertSame(20.0, $settings->get('max_execution_time'));
        self::assertSame(1, $settings->get('enable_http_compression'));
        self::assertFalse($settings->get('https'));
    }

    public function testGetReturnsValueForExistingKey(): void
    {
        $settings = new Settings();

        self::assertFalse($settings->get('extremes'));
        self::assertTrue($settings->get('readonly'));
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        $settings = new Settings();

        self::assertNull($settings->get('nonexistent_key'));
    }

    public function testIsReturnsTrueForExistingKey(): void
    {
        $settings = new Settings();

        self::assertTrue($settings->is('extremes'));
        self::assertTrue($settings->is('readonly'));
    }

    public function testIsReturnsFalseForMissingKey(): void
    {
        $settings = new Settings();

        self::assertFalse($settings->is('nonexistent_key'));
    }

    public function testSetStoresValueAndReturnsThis(): void
    {
        $settings = new Settings();

        $result = $settings->set('custom_key', 'custom_value');

        self::assertSame($settings, $result);
        self::assertSame('custom_value', $settings->get('custom_key'));
    }

    public function testSetChaining(): void
    {
        $settings = new Settings();

        $settings->set('a', 1)->set('b', 2)->set('c', 3);

        self::assertSame(1, $settings->get('a'));
        self::assertSame(2, $settings->get('b'));
        self::assertSame(3, $settings->get('c'));
    }

    public function testDatabaseSetsAndGetDatabaseRetrieves(): void
    {
        $settings = new Settings();

        self::assertNull($settings->getDatabase());

        $result = $settings->database('test_db');

        self::assertSame($settings, $result);
        self::assertSame('test_db', $settings->getDatabase());
    }

    public function testGetTimeOutReturnsIntFromMaxExecutionTime(): void
    {
        $settings = new Settings();

        // Default is 20.0 (float), getTimeOut() casts to int
        self::assertSame(20, $settings->getTimeOut());

        $settings->max_execution_time(60);
        self::assertSame(60, $settings->getTimeOut());
    }

    public function testSessionIdSetAndGet(): void
    {
        $settings = new Settings();

        $result = $settings->session_id('my-session-123');

        self::assertSame($settings, $result);
        self::assertSame('my-session-123', $settings->getSessionId());
    }

    public function testGetSessionIdReturnsFalseWhenNoSession(): void
    {
        $settings = new Settings();

        self::assertFalse($settings->getSessionId());
    }

    public function testMakeSessionIdGeneratesAndReturnsSessionId(): void
    {
        $settings = new Settings();

        $sessionId = $settings->makeSessionId();

        self::assertIsString($sessionId);
        self::assertNotEmpty($sessionId);
        // SHA1 produces 40 hex characters
        self::assertSame(40, strlen($sessionId));
        // Subsequent call to getSessionId returns the same value
        self::assertSame($sessionId, $settings->getSessionId());
    }

    public function testEnableHttpCompressionAndIsEnableHttpCompression(): void
    {
        $settings = new Settings();

        // Default is 1
        self::assertSame(1, $settings->isEnableHttpCompression());

        $result = $settings->enableHttpCompression(false);

        self::assertSame($settings, $result);
        self::assertSame(0, $settings->isEnableHttpCompression());

        $settings->enableHttpCompression(true);
        self::assertSame(1, $settings->isEnableHttpCompression());
    }

    public function testHttpsAndIsHttps(): void
    {
        $settings = new Settings();

        // Default is false
        self::assertFalse($settings->isHttps());

        $result = $settings->https(true);

        self::assertSame($settings, $result);
        self::assertTrue($settings->isHttps());

        $settings->https(false);
        self::assertFalse($settings->isHttps());
    }

    public function testHttpsDefaultParameterIsTrue(): void
    {
        $settings = new Settings();

        $settings->https();

        self::assertTrue($settings->isHttps());
    }

    public function testReadonlySetsFlag(): void
    {
        $settings = new Settings();

        $result = $settings->readonly(false);

        self::assertSame($settings, $result);
        self::assertFalse($settings->get('readonly'));

        $settings->readonly(2);
        self::assertSame(2, $settings->get('readonly'));
    }

    public function testMaxExecutionTimeSetsTimeout(): void
    {
        $settings = new Settings();

        $result = $settings->max_execution_time(120);

        self::assertSame($settings, $result);
        self::assertSame(120, $settings->get('max_execution_time'));
        self::assertSame(120, $settings->getTimeOut());
    }

    public function testApplyAppliesArrayOfSettings(): void
    {
        $settings = new Settings();

        $result = $settings->apply([
            'custom1' => 'value1',
            'custom2' => 42,
            'readonly' => false,
        ]);

        self::assertSame($settings, $result);
        self::assertSame('value1', $settings->get('custom1'));
        self::assertSame(42, $settings->get('custom2'));
        self::assertFalse($settings->get('readonly'));
    }

    public function testSetReadOnlyUserAndIsReadOnlyUserWithBool(): void
    {
        $settings = new Settings();

        // Default is false
        self::assertFalse($settings->isReadOnlyUser());

        $settings->setReadOnlyUser(true);
        self::assertTrue($settings->isReadOnlyUser());

        $settings->setReadOnlyUser(false);
        self::assertFalse($settings->isReadOnlyUser());
    }

    public function testSetReadOnlyUserAndIsReadOnlyUserWithInt(): void
    {
        $settings = new Settings();

        $settings->setReadOnlyUser(1);
        self::assertTrue($settings->isReadOnlyUser());

        $settings->setReadOnlyUser(0);
        self::assertFalse($settings->isReadOnlyUser());
    }

    public function testGetSettingReturnsNullForMissingSetting(): void
    {
        $settings = new Settings();

        self::assertNull($settings->getSetting('nonexistent'));
    }

    public function testGetSettingReturnsValueForExistingSetting(): void
    {
        $settings = new Settings();

        self::assertSame(1, $settings->getSetting('enable_http_compression'));
    }

    public function testGetSettingsReturnsFullArray(): void
    {
        $settings = new Settings();

        $all = $settings->getSettings();

        self::assertArrayHasKey('extremes', $all);
        self::assertArrayHasKey('readonly', $all);
        self::assertArrayHasKey('max_execution_time', $all);
        self::assertArrayHasKey('enable_http_compression', $all);
        self::assertArrayHasKey('https', $all);
        self::assertCount(5, $all);
    }

    public function testClearEmptiesSettings(): void
    {
        $settings = new Settings();

        self::assertNotEmpty($settings->getSettings());

        $settings->clear();

        self::assertEmpty($settings->getSettings());
        self::assertNull($settings->get('readonly'));
    }
}
