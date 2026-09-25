<?php

declare(strict_types=1);

namespace ClickHouseDB\Query;

use ClickHouseDB\Exception\QueryException;

use function implode;
use function is_readable;
use function realpath;
use function sizeof;

class WhereInFile
{
    public const FORMAT_TabSeparated          = 'TabSeparated';
    public const FORMAT_TabSeparatedWithNames = 'TabSeparatedWithNames';
    public const FORMAT_CSV                   = 'CSV';

    private array $_files = [];

    public function __construct()
    {
    }

    public function attachFile(string $file_name, string $table_name, string|array $structure, string $format = 'CSV'): void
    {
        if (! is_readable($file_name)) {
            throw new QueryException('Can`t read file: ' . $file_name);
        }

        $this->_files[$table_name] = [
            'filename'  => $file_name,
            'structure' => $structure,
            'format'    => $format,
        ];
    }

    public function size(): int
    {
        return sizeof($this->_files);
    }

    public function fetchFiles(): array
    {
        $out = [];
        foreach ($this->_files as $table => $data) {
            $out[$table] = realpath($data['filename']);
        }

        return $out;
    }

    public function fetchStructure(string $table): string
    {
        $structure = $this->_files[$table]['structure'];

        $out = [];
        foreach ($structure as $name => $type) {
            $out[] = $name . ' ' . $type;
        }

        return implode(',', $out);
    }

    public function fetchUrlParams(): array
    {
        $out = [];
        foreach ($this->_files as $table => $data) {
            $out[$table . '_structure'] = $this->fetchStructure($table);
            $out[$table . '_format']    = $data['format'];
        }

        return $out;
    }
}
