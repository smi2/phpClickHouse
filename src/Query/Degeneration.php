<?php
namespace ClickHouseDB\Query;

interface Degeneration
{
    public function process(string $sql): string;
    public function bindParams(array $bindings): void;
    public function getBind(): array;
}