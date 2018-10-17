<?php

namespace ClickHouseDB\Query\Degeneration;

use ClickHouseDB\Query\Degeneration;

class Conditions implements Degeneration
{
    /**
     * @var array
     */
    protected $bindings = [];

    /**
     * @param array $bindings
     */
    public function bindParams(array $bindings)
    {
        foreach ($bindings as $column => $value) {
            $this->bindings[$column] = $value;
        }
    }


    static function __ifsets($matches, $markers, $else = false)
    {
        $content_false = '';

        if ($else)
        {
            list($condition, $preset, $variable, $content_true, $content_false) = $matches;
        } else
        {
            list($condition, $preset, $variable, $content_true) = $matches;
        }
        $preset = strtolower($preset);

        if ($preset == 'set')
        {
            return (isset($markers[$variable]) && !empty($markers[$variable])) ? $content_true : $content_false;
        }
        if ($preset == 'bool')
        {
            return (isset($markers[$variable]) && is_bool($markers[$variable]) && $markers[$variable] == true)
                ? $content_true
                : $content_false;
        }
        if ($preset == 'string')
        {
            return (isset($markers[$variable]) && is_string($markers[$variable]) && strlen($markers[$variable]))
                ? $content_true
                : $content_false;
        }
        if ($preset == 'int')
        {
            return (isset($markers[$variable]) && intval($markers[$variable]) <> 0)
                ? $content_true
                : $content_false;
        }

        return '';
    }

    /**
     * @param string $sql
     * @return mixed
     */
    public function process($sql)
    {
        $markers = $this->bindings;

        // 2. process if/else conditions
        $sql = preg_replace_callback('#\{if\s(.+?)}(.+?)\{else}(.+?)\{/if}#sui', function($matches) use ($markers) {
            list($condition, $variable, $content_true, $content_false) = $matches;

            return (isset($markers[$variable]) && ($markers[$variable] || is_numeric($markers[$variable])))
                ? $content_true
                : $content_false;
        }, $sql);

        // 3. process if conditions
        $sql = preg_replace_callback('#\{if\s(.+?)}(.+?)\{/if}#sui', function($matches) use ($markers) {
            list($condition, $variable, $content) = $matches;

            if (isset($markers[$variable]) && ($markers[$variable] || is_numeric($markers[$variable]))) {
                return $content;
            }
        }, $sql);

        // 1. process if[set|int]/else conditions
        $sql = preg_replace_callback('#\{if(.{1,}?)\s(.+?)}(.+?)\{else}(.+?)\{/if}#sui', function($matches) use ($markers) {return  self::__ifsets($matches, $markers, true); }, $sql);
        $sql = preg_replace_callback('#\{if(.{1,}?)\s(.+?)}(.+?)\{/if}#sui', function($matches) use ($markers) { return self::__ifsets($matches, $markers, false); }, $sql);

        return $sql;
    }

}
