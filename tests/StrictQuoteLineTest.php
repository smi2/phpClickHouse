<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Quote\CSV;
use ClickHouseDB\Quote\FormatLine;
use ClickHouseDB\Quote\StrictQuoteLine;
use PHPUnit\Framework\TestCase;

class StrictQuoteLineTest extends TestCase
{
    // ---------------------------------------------------------------
    // StrictQuoteLine — CSV format
    // ---------------------------------------------------------------

    public function testQuoteRowCsvSimple(): void
    {
        $quoter = new StrictQuoteLine('CSV');
        // Strings wrapped in double-quote enclosure, comma delimiter
        $result = $quoter->quoteRow(['hello', 'world']);
        self::assertSame('"hello","world"', $result);
    }

    public function testQuoteRowCsvWithNumbers(): void
    {
        $quoter = new StrictQuoteLine('CSV');
        // Numerics pass through without enclosure
        $result = $quoter->quoteRow([1, 2.5, 'text']);
        self::assertSame('1,2.5,"text"', $result);
    }

    public function testQuoteRowCsvEmbeddedDelimiter(): void
    {
        $quoter = new StrictQuoteLine('CSV');
        // A string containing a comma should still be enclosed
        $result = $quoter->quoteRow(['one,two']);
        self::assertSame('"one,two"', $result);
    }

    public function testQuoteRowCsvEmbeddedQuote(): void
    {
        $quoter = new StrictQuoteLine('CSV');
        // Double-quote inside value gets doubled (encodeEnclosure = ")
        $result = $quoter->quoteRow(['say "hi"']);
        self::assertSame('"say ""hi"""', $result);
    }

    // ---------------------------------------------------------------
    // StrictQuoteLine — TSV format
    // ---------------------------------------------------------------

    public function testQuoteRowTsvSimple(): void
    {
        $quoter = new StrictQuoteLine('TSV');
        $result = $quoter->quoteRow(['a', 'b', 'c']);
        // TSV: tab delimiter, tab-encode mode replaces tabs/newlines
        self::assertSame("a\tb\tc", $result);
    }

    public function testQuoteRowTsvEmbeddedTab(): void
    {
        $quoter = new StrictQuoteLine('TSV');
        $result = $quoter->quoteRow(["col\t1"]);
        // Embedded tab is escaped to literal \t
        self::assertSame('col\\t1', $result);
    }

    public function testQuoteRowTsvEmbeddedNewline(): void
    {
        $quoter = new StrictQuoteLine('TSV');
        $result = $quoter->quoteRow(["line\nbreak"]);
        self::assertSame('line\\nbreak', $result);
    }

    // ---------------------------------------------------------------
    // StrictQuoteLine — Insert format
    // ---------------------------------------------------------------

    public function testQuoteRowInsertSimple(): void
    {
        $quoter = new StrictQuoteLine('Insert');
        $result = $quoter->quoteRow(['hello', 42]);
        // Insert: single-quote enclosure, backslash encodeEnclosure
        self::assertSame("'hello',42", $result);
    }

    public function testQuoteRowInsertEmbeddedQuote(): void
    {
        $quoter = new StrictQuoteLine('Insert');
        // Single-quote in value gets escaped with backslash
        $result = $quoter->quoteRow(["it's"]);
        self::assertSame("'it\\'s'", $result);
    }

    public function testQuoteRowInsertEmbeddedBackslash(): void
    {
        $quoter = new StrictQuoteLine('Insert');
        $result = $quoter->quoteRow(['back\\slash']);
        // Backslash is both encodeEnclosure and gets escaped
        self::assertSame("'back\\\\slash'", $result);
    }

    // ---------------------------------------------------------------
    // StrictQuoteLine::quoteValue()
    // ---------------------------------------------------------------

    public function testQuoteValueReturnsArray(): void
    {
        $quoter = new StrictQuoteLine('CSV');
        $result = $quoter->quoteValue(['a', 1, null]);
        self::assertCount(3, $result);
        self::assertSame('"a"', $result[0]);
        self::assertSame('1', $result[1]);
        // CSV null representation is \N
        self::assertSame('\\N', $result[2]);
    }

    // ---------------------------------------------------------------
    // Null handling
    // ---------------------------------------------------------------

    public function testNullValueCsv(): void
    {
        $quoter = new StrictQuoteLine('CSV');
        $result = $quoter->quoteRow([null]);
        self::assertSame('\\N', $result);
    }

    public function testNullValueInsert(): void
    {
        $quoter = new StrictQuoteLine('Insert');
        $result = $quoter->quoteRow([null]);
        self::assertSame('NULL', $result);
    }

    public function testNullValueTsv(): void
    {
        $quoter = new StrictQuoteLine('TSV');
        $result = $quoter->quoteRow([null]);
        self::assertSame(' ', $result);
    }

    // ---------------------------------------------------------------
    // Numeric values
    // ---------------------------------------------------------------

    public function testIntegerValue(): void
    {
        $quoter = new StrictQuoteLine('CSV');
        $result = $quoter->quoteRow([42]);
        self::assertSame('42', $result);
    }

    public function testFloatValue(): void
    {
        $quoter = new StrictQuoteLine('CSV');
        $result = $quoter->quoteRow([3.14]);
        self::assertSame('3.14', $result);
    }

    public function testNegativeNumber(): void
    {
        $quoter = new StrictQuoteLine('Insert');
        $result = $quoter->quoteRow([-7, -0.5]);
        self::assertSame('-7,-0.5', $result);
    }

    // ---------------------------------------------------------------
    // Boolean handling
    // ---------------------------------------------------------------

    public function testBooleanTrueInsert(): void
    {
        $quoter = new StrictQuoteLine('Insert');
        $result = $quoter->quoteRow([true]);
        self::assertSame("'true'", $result);
    }

    public function testBooleanFalseInsert(): void
    {
        $quoter = new StrictQuoteLine('Insert');
        $result = $quoter->quoteRow([false]);
        self::assertSame("'false'", $result);
    }

    public function testBooleanCsv(): void
    {
        $quoter = new StrictQuoteLine('CSV');
        $result = $quoter->quoteRow([true, false]);
        self::assertSame('"true","false"', $result);
    }

    // ---------------------------------------------------------------
    // Empty array row
    // ---------------------------------------------------------------

    public function testEmptyArrayRow(): void
    {
        $quoter = new StrictQuoteLine('CSV');
        $result = $quoter->quoteRow([]);
        self::assertSame('', $result);
    }

    // ---------------------------------------------------------------
    // encodeString()
    // ---------------------------------------------------------------

    public function testEncodeStringEscapesEnclosure(): void
    {
        $quoter = new StrictQuoteLine('CSV');
        // CSV: enclosure_esc = ", encode_esc = "
        $encoded = $quoter->encodeString('say "hi"', '"', '"');
        self::assertSame('say ""hi""', $encoded);
    }

    public function testEncodeStringEscapesBackslash(): void
    {
        $quoter = new StrictQuoteLine('Insert');
        // Insert: enclosure_esc = ' , encode_esc = \\
        $encoded = $quoter->encodeString("it's a \\path", "'", '\\\\');
        self::assertSame("it\\'s a \\\\path", $encoded);
    }

    // ---------------------------------------------------------------
    // skipEncode = true
    // ---------------------------------------------------------------

    public function testQuoteRowSkipEncode(): void
    {
        $quoter = new StrictQuoteLine('CSV');
        // With skipEncode, string value is wrapped in enclosure but NOT encoded
        $result = $quoter->quoteRow(['say "hi"'], true);
        self::assertSame('"say "hi""', $result);
    }

    public function testQuoteRowSkipEncodeInsert(): void
    {
        $quoter = new StrictQuoteLine('Insert');
        $result = $quoter->quoteRow(["it's"], true);
        // No encoding, but still wrapped in single-quotes
        self::assertSame("'it's'", $result);
    }

    // ---------------------------------------------------------------
    // Unsupported format
    // ---------------------------------------------------------------

    public function testUnsupportedFormatThrows(): void
    {
        $this->expectException(\ClickHouseDB\Exception\QueryException::class);
        new StrictQuoteLine('XML');
    }

    // ---------------------------------------------------------------
    // FormatLine static methods
    // ---------------------------------------------------------------

    public function testFormatLineCsv(): void
    {
        $result = FormatLine::CSV(['hello', 42, null]);
        self::assertSame('"hello",42,\\N', $result);
    }

    public function testFormatLineTsv(): void
    {
        $result = FormatLine::TSV(['a', 'b']);
        self::assertSame("a\tb", $result);
    }

    public function testFormatLineInsert(): void
    {
        $result = FormatLine::Insert(['text', 100]);
        self::assertSame("'text',100", $result);
    }

    public function testFormatLineInsertWithSkipEncode(): void
    {
        $result = FormatLine::Insert(["it's"], true);
        self::assertSame("'it's'", $result);
    }

    // ---------------------------------------------------------------
    // CSV class (deprecated compatibility wrapper)
    // ---------------------------------------------------------------

    public function testCsvQuoteRowDelegatesToFormatLine(): void
    {
        $viaCSV = CSV::quoteRow(['hello', 42]);
        $viaFormatLine = FormatLine::CSV(['hello', 42]);
        self::assertSame($viaFormatLine, $viaCSV);
    }

    public function testCsvQuoteRowOutput(): void
    {
        $result = CSV::quoteRow(['one', 'two', 3]);
        self::assertSame('"one","two",3', $result);
    }
}
