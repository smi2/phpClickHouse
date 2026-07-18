<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests;

use ClickHouseDB\Exception\QueryException;
use ClickHouseDB\Query\WriteToFile;
use PHPUnit\Framework\TestCase;

class WriteToFileTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpch_writetofile_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up any files created during tests
        $files = glob($this->tmpDir . '/*');
        if ($files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testConstructorWithValidFileCreatesObject(): void
    {
        $filePath = $this->tmpDir . '/test_output.csv';

        $writer = new WriteToFile($filePath);

        self::assertInstanceOf(WriteToFile::class, $writer);
        self::assertSame($filePath, $writer->fetchFile());
    }

    public function testConstructorWithEmptyFilenameThrowsException(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Bad file path');

        new WriteToFile('');
    }

    public function testConstructorWithNonWritablePathThrowsException(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Can`t writable dir');

        new WriteToFile('/nonexistent_dir_12345/output.csv');
    }

    public function testConstructorWithFormatParameterSetsFormat(): void
    {
        $filePath = $this->tmpDir . '/test_output.tsv';

        $writer = new WriteToFile($filePath, true, 'TabSeparated');

        self::assertSame('TabSeparated', $writer->fetchFormat());
    }

    public function testConstructorOverwriteDeletesExistingFile(): void
    {
        $filePath = $this->tmpDir . '/existing_file.csv';
        file_put_contents($filePath, 'old data');
        self::assertFileExists($filePath);

        $writer = new WriteToFile($filePath, true);

        // File should have been deleted by constructor
        self::assertFileDoesNotExist($filePath);
        self::assertSame($filePath, $writer->fetchFile());
    }

    public function testConstructorOverwriteFalseThrowsOnExistingFile(): void
    {
        $filePath = $this->tmpDir . '/existing_file.csv';
        file_put_contents($filePath, 'old data');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('File exists');

        new WriteToFile($filePath, false);
    }

    public function testDefaultFormatIsCsv(): void
    {
        $filePath = $this->tmpDir . '/test_output.csv';

        $writer = new WriteToFile($filePath);

        self::assertSame('CSV', $writer->fetchFormat());
    }

    public function testSetFormatWithValidFormatSucceeds(): void
    {
        $filePath = $this->tmpDir . '/test_output.csv';
        $writer = new WriteToFile($filePath);

        $writer->setFormat('JSONEachRow');

        self::assertSame('JSONEachRow', $writer->fetchFormat());
    }

    public function testSetFormatWithAllSupportedFormats(): void
    {
        $formats = [
            WriteToFile::FORMAT_TabSeparated,
            WriteToFile::FORMAT_TabSeparatedWithNames,
            WriteToFile::FORMAT_CSV,
            WriteToFile::FORMAT_CSVWithNames,
            WriteToFile::FORMAT_JSONEACHROW,
        ];

        foreach ($formats as $format) {
            $filePath = $this->tmpDir . '/test_' . $format . '.dat';
            $writer = new WriteToFile($filePath);
            $writer->setFormat($format);
            self::assertSame($format, $writer->fetchFormat());
        }
    }

    public function testSetFormatWithInvalidFormatThrowsException(): void
    {
        $filePath = $this->tmpDir . '/test_output.csv';
        $writer = new WriteToFile($filePath);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Unsupport format');

        $writer->setFormat('InvalidFormat');
    }

    public function testGetGzipReturnsFalseByDefault(): void
    {
        $filePath = $this->tmpDir . '/test_output.csv';
        $writer = new WriteToFile($filePath);

        self::assertFalse($writer->getGzip());
    }

    public function testSetGzipAndGetGzip(): void
    {
        $filePath = $this->tmpDir . '/test_output.csv';
        $writer = new WriteToFile($filePath);

        $writer->setGzip(true);
        self::assertTrue($writer->getGzip());

        $writer->setGzip(false);
        self::assertFalse($writer->getGzip());
    }

    public function testFetchFileReturnsFilename(): void
    {
        $filePath = $this->tmpDir . '/my_data.csv';
        $writer = new WriteToFile($filePath);

        self::assertSame($filePath, $writer->fetchFile());
    }

    public function testFetchFormatReturnsFormatString(): void
    {
        $filePath = $this->tmpDir . '/test.csv';
        $writer = new WriteToFile($filePath, true, 'CSVWithNames');

        self::assertSame('CSVWithNames', $writer->fetchFormat());
    }

    public function testFormatConstants(): void
    {
        self::assertSame('TabSeparated', WriteToFile::FORMAT_TabSeparated);
        self::assertSame('TabSeparatedWithNames', WriteToFile::FORMAT_TabSeparatedWithNames);
        self::assertSame('CSV', WriteToFile::FORMAT_CSV);
        self::assertSame('CSVWithNames', WriteToFile::FORMAT_CSVWithNames);
        self::assertSame('JSONEachRow', WriteToFile::FORMAT_JSONEACHROW);
    }
}
