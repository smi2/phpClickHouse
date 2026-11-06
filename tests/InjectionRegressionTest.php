<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Exception\DatabaseException;
use ClickHouseDB\Exception\QueryException;
use ClickHouseDB\Query\Expression\Func\UUIDStringToNum;
use ClickHouseDB\Quote\FormatLine;
use ClickHouseDB\Type\MapType;
use ClickHouseDB\Type\StringType;
use ClickHouseDB\Type\TupleType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for injection paths found in the 2026-11 security audit.
 * Each payload must be stored/compared literally and must never execute.
 */
#[Group('InjectionRegressionTest')]
final class InjectionRegressionTest extends TestCase
{
    use WithClient;

    private const SECRET = 'TOP-SECRET';

    protected function setUp(): void
    {
        $this->client->write('DROP TABLE IF EXISTS inj_secret');
        $this->client->write('CREATE TABLE inj_secret (s String) ENGINE = Memory');
        $this->client->write("INSERT INTO inj_secret VALUES ('" . self::SECRET . "')");
    }

    protected function tearDown(): void
    {
        foreach (['inj_secret', 'inj_dst', 'inj_arr', 'inj_tuple', 'inj_map'] as $table) {
            $this->client->write('DROP TABLE IF EXISTS ' . $table);
        }
    }

    // ------------------------------------------------- F1: column identifiers

    public function testInsertAssocBulkColumnNameCannotBreakOutOfIdentifier(): void
    {
        $this->client->write('CREATE TABLE inj_dst (a String) ENGINE = Memory');

        try {
            $this->client->insertAssocBulk('inj_dst', ['a`) SELECT s FROM inj_secret -- ' => 'x']);
            $this->fail('Expected the malicious column name to be rejected by the server');
        } catch (DatabaseException | QueryException $e) {
            // the whole string is now a single (unknown) column name
        }

        $this->assertSame(0, (int) $this->client->select('SELECT count() AS c FROM inj_dst')->fetchOne('c'));
    }

    public function testInsertQuotesIdentifiersExactly(): void
    {
        $this->client->write('CREATE TABLE inj_dst (a String, `b\\`c` String) ENGINE = Memory');

        $this->client->insert('inj_dst', [['x', 'y']], ['a', 'b`c']);

        $this->assertSame([['a' => 'x', 'b`c' => 'y']], $this->client->select('SELECT * FROM inj_dst')->rows());
    }

    // ------------------------------- F2: objects inside arrays / Tuple / Map

    public function testStringTypeInsideArrayIsEscaped(): void
    {
        $payload = "q']), ((SELECT any(s) FROM inj_secret), ['z";
        $this->client->write('CREATE TABLE inj_arr (id String, arr Array(String)) ENGINE = Memory');

        $this->client->insert('inj_arr', [['b1', [StringType::fromString($payload)]]], ['id', 'arr']);

        $this->assertSame(
            [['id' => 'b1', 'arr' => [$payload]]],
            $this->client->select('SELECT id, arr FROM inj_arr')->rows()
        );
    }

    public function testStringTypeInsideTupleIsEscaped(): void
    {
        $payload = "v')), ((SELECT any(s) FROM inj_secret), ('y','z";
        $this->client->write('CREATE TABLE inj_tuple (t Tuple(String, String)) ENGINE = Memory');

        $this->client->insert('inj_tuple', [[TupleType::fromArray(['k', StringType::fromString($payload)])]], ['t']);

        $this->assertSame([['k', $payload]], array_column($this->client->select('SELECT t FROM inj_tuple')->rows(), 't'));
    }

    public function testStringTypeInsideMapIsEscaped(): void
    {
        $payload = "v'), ((SELECT any(s) FROM inj_secret)";
        $this->client->write('CREATE TABLE inj_map (m Map(String, String)) ENGINE = Memory');

        $this->client->insert('inj_map', [[MapType::fromArray(['k' => StringType::fromString($payload)])]], ['m']);

        $this->assertSame(['k' => $payload], $this->client->select('SELECT m FROM inj_map')->fetchOne('m'));
    }

    public function testCsvArrayElementsAreEscaped(): void
    {
        $this->assertSame(
            "\"['s\"\"t,x']\"",
            FormatLine::CSV([[StringType::fromString('s"t,x')]])
        );
    }

    // ------------------------------------------------------- F3: isExists()

    public function testIsExistsDoesNotInterpolateNames(): void
    {
        $this->assertSame([], $this->client->isExists('default', "zzz' OR name LIKE 'inj%' OR '1'='0"));
        $this->assertArrayHasKey('inj_secret', $this->client->isExists($this->client->settings()->getDatabase(), 'inj_secret'));
    }

    // ------------------------------------------------------- F7: verbose()

    public function testVerboseFlagIsHonoured(): void
    {
        $this->assertFalse($this->client->verbose(false));
        $this->assertTrue($this->client->verbose(true));
        $this->assertFalse($this->client->verbose(false));
    }

    // ---------------------------------------------- F9: UUIDStringToNum

    public function testUuidStringToNumEscapesArgument(): void
    {
        $this->assertSame("UUIDStringToNum('x\\') OR 1=1 --')", (new UUIDStringToNum("x') OR 1=1 --"))->getValue());

        $uuid = '0f372656-6a5b-4727-a4c4-f6357775d926';
        $this->assertSame(
            $uuid,
            $this->client->select('SELECT UUIDNumToString(' . (new UUIDStringToNum($uuid))->getValue() . ') AS u')->fetchOne('u')
        );
    }

    // ---------------------------------------------------------- F10: TSV

    public function testTsvEscapesBackslash(): void
    {
        $this->assertSame("user\\\\\tx\\ty\tline\\nbreak", FormatLine::TSV(['user\\', "x\ty", "line\nbreak"]));

        $this->client->write('CREATE TABLE inj_dst (a String, b String, c String) ENGINE = Memory');
        $file = $this->tmpPath . '/inj_tsv_' . getmypid() . '.tsv';
        file_put_contents($file, FormatLine::TSV(['C:\\new\\dir', 'tail\\', "x\ty"]) . "\n");
        try {
            $this->client->insertBatchTSVFiles('inj_dst', [$file], ['a', 'b', 'c']);
        } finally {
            unlink($file);
        }

        $this->assertSame(
            [['a' => 'C:\\new\\dir', 'b' => 'tail\\', 'c' => "x\ty"]],
            $this->client->select('SELECT a, b, c FROM inj_dst')->rows()
        );
    }
}
