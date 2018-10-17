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

    /**
     * @var array
     */
    private $_files = [];


    /**
     * WhereInFile constructor.
     */
    public function __construct() {}


    /**
     * @param string $file_name
     * @param string $table_name
     * @param string $structure
     * @param string $format
     */
    public function attachFile($file_name, $table_name, $structure, $format = 'CSV')
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

    /**
     * @return int
     */
    public function size()
    {
        return sizeof($this->_files);
    }

    /**
     * @return array
     */
    public function fetchFiles()
    {
        $out = [];
        foreach ($this->_files as $table => $data) {
            $out[$table] = realpath($data['filename']);
        }

        return $out;
    }

    /**
     * @param string $table
     * @return string
     */
    public function fetchStructure($table)
    {
        $structure = $this->_files[$table]['structure'];

        $out = [];
        foreach ($structure as $name => $type) {
            $out[] = $name . ' ' . $type;
        }

        return implode(',', $out);
    }

    /**
     * @return array
     */
    public function fetchUrlParams()
    {
        $out = [];
        foreach ($this->_files as $table => $data) {
            $out[$table . '_structure'] = $this->fetchStructure($table);
            $out[$table . '_format'] = $data['format'];
        }

        return $out;
    }

}
