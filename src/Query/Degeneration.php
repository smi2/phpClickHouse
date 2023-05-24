<?php
namespace ClickHouseDB\Query;

interface Degeneration
{
    public function process($sql);
    public function bindParams(array $bindings);
    public function getBind():array;
}