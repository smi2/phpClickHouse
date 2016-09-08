<?php

namespace ClickHouseDB\Query\Degeneration;

/**
 * Class QueryDegeneration
 * @package ClickHouseDB
 */

class Conditions implements \ClickHouseDB\Query\Degeneration
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
            $this->bindings[$column]=$value;
        }
    }

    /**
     * @param $sql
     * @return mixed
     */
    public function process($sql)
    {
        $markers=$this->bindings;
        // 2. process if/else conditions
        $sql = preg_replace_callback('#\{if\s(.+?)}(.+?)\{else}(.+?)\{/if}#sui', function ($matches) use ($markers) {
            list($condition, $variable, $content_true, $content_false) = $matches;

            return (isset($markers[$variable]) && $markers[$variable])
                ? $content_true
                : $content_false;
        }, $sql);

        // 3. process if conditions
        $sql = preg_replace_callback('#\{if\s(.+?)}(.+?)\{/if}#sui', function ($matches) use ($markers) {
            list($condition, $variable, $content) = $matches;

            if (isset($markers[$variable]) && $markers[$variable]) {
                return $content;
            }
        }, $sql);

        return $sql;
    }

}