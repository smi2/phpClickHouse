<?php

namespace ClickHouseDB\Query;

use ClickHouseDB\Exception\QueryException;

class WhereInFile
{
    /**
     *
     */
    const FORMAT_TabSeparated          = 'TabSeparated';
    const FORMAT_TabSeparatedWithNames = 'TabSeparatedWithNames';
    const FORMAT_CSV                   = 'CSV';

    private array $_files = [];


    /**
     * WhereInFile constructor.
     */
    public function __construct() {}


    public function attachFile(string $file_name, string $table_name, array $structure, string $format = 'CSV'): void
    {
        if (!is_readable($file_name)) {
            throw new QueryException('Can`t read file: ' . $file_name);
        }

        $this->_files[$table_name] = [
            'filename'  => $file_name,
            'structure' => $structure,
            'format'    => $format
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
            $out[$table . '_format'] = $data['format'];
        }

        return $out;
    }

}
