<?php

namespace ClickHouseDB\Query;

use ClickHouseDB\Exception\QueryException;

class WriteToFile
{
    /**
     *
     */
    const FORMAT_TabSeparated          = 'TabSeparated';
    const FORMAT_TabSeparatedWithNames = 'TabSeparatedWithNames';
    const FORMAT_CSV                   = 'CSV';
    const FORMAT_CSVWithNames          = 'CSVWithNames';
    const FORMAT_JSONEACHROW           = 'JSONEachRow';

    private array $support_format = ['TabSeparated', 'TabSeparatedWithNames', 'CSV', 'CSVWithNames', 'JSONEachRow'];
    private ?string $file_name = null;

    private string $format = 'CSV';

    private bool $gzip = false;
    /**
     * WriteToFile constructor.
     */
    public function __construct(string $file_name, bool $overwrite = true, ?string $format = null) {


        if (!$file_name)
        {
            throw new QueryException('Bad file path');
        }

        if (is_file($file_name))
        {
            if (!$overwrite)
            {
                throw new QueryException('File exists: ' . $file_name);
            }
            if (!unlink($file_name))
            {
                throw new QueryException('Can`t delete: ' . $file_name);
            }
        }
        $dir = dirname($file_name);
        if (!is_writable($dir))
        {
            throw new QueryException('Can`t writable dir: ' . $dir);
        }
        if (is_string($format))
        {
            $this->setFormat($format);
        }
        $this->file_name = $file_name;
    }

    public function getGzip(): bool
    {
        return $this->gzip;
    }

    public function setGzip(bool $flag): void
    {
        $this->gzip = $flag;
    }

    public function setFormat(string $format): void
    {
        if (!in_array($format, $this->support_format))
        {
            throw new QueryException('Unsupport format: ' . $format);
        }
        $this->format = $format;
    }
    public function size(): int
    {
        return filesize($this->file_name);
    }

    public function fetchFile(): string
    {
        return $this->file_name;
    }

    public function fetchFormat(): string
    {
        return $this->format;
    }

}
