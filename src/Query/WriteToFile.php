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

    private $support_format = ['TabSeparated', 'TabSeparatedWithNames', 'CSV', 'CSVWithNames', 'JSONEachRow'];
    /**
     * @var string
     */
    private $file_name = null;

    /**
     * @var string
     */
    private $format = 'CSV';

    /**
     * @var bool
     */
    private $gzip = false;
    /**
     * WriteToFile constructor.
     * @param string $file_name
     * @param bool $overwrite
     * @param string|null $format
     */
    public function __construct($file_name, $overwrite = true, $format = null) {


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

    /**
     * @return bool
     */
    public function getGzip()
    {
        return $this->gzip;
    }

    /**
     * @param bool $flag
     */
    public function setGzip($flag)
    {
        $this->gzip = $flag;
    }

    /**
     * @param string $format
     */
    public function setFormat($format)
    {
        if (!in_array($format, $this->support_format))
        {
            throw new QueryException('Unsupport format: ' . $format);
        }
        $this->format = $format;
    }
    /**
     * @return int
     */
    public function size()
    {
        return filesize($this->file_name);
    }

    /**
     * @return string
     */
    public function fetchFile()
    {
        return $this->file_name;
    }

    /**
     * @return string
     */
    public function fetchFormat()
    {
        return $this->format;
    }

}
