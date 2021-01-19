<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Quote\StrictQuoteLine;
use PHPUnit\Framework\TestCase;
use function array_diff;
use function array_map;
use function file_put_contents;
use function unlink;
use const FILE_APPEND;

class StrictQuoteLineTest extends TestCase
{
    use WithClient;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->client->write('DROP TABLE IF EXISTS cities');
        $this->client->write('
            CREATE TABLE IF NOT EXISTS cities (
                date Date,
                city String,
                keywords Array(String),
                nums Array(UInt8)
            ) ENGINE = MergeTree(date, (date), 8192)
        ');
        parent::setUp();
    }

    /**
     * @group test
     *
     * @return void
     */
    public function testQuoteValueCSV()
    {
        $strict = new StrictQuoteLine('CSV');

        $rows = [
            ['2018-04-01', '"That works"', ['\"That does not\"', 'That works'], [8, 7]],
            ['2018-04-02', 'That works', ['\""That does not\""', '"\'\""That works"""\"'], [1, 0]],
            ['2018-04-03', 'That works', ['\"\"That does not"\'""', '""""That works""""'], [9, 121]],
        ];

        $fileName = $this->tmpPath . '__test_quote_value.csv';

        @unlink($fileName);
        foreach ($rows as $row) {
            file_put_contents($fileName, $strict->quoteRow($row) . "\n", FILE_APPEND);
        }

        $this->client->insertBatchFiles('cities', [$fileName], ['date', 'city', 'keywords', 'nums']);
        $statement = $this->client->select('SELECT * FROM cities');

        $result = array_map('array_values', $statement->rows());
        foreach ($result as $key => $value) {
            // check correct quote string
            $this->assertEmpty(array_diff($rows[$key][2], $value[2]));
            $this->assertEmpty(array_diff($rows[$key][3], $value[3]));
        }

        $rows[0][2][1] = 'Not the same string';
        $this->assertCount(1, array_diff($rows[0][2], $result[0][2]));
    }
}
