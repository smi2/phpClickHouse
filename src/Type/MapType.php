<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

final class MapType implements Type
{
    public array $value;

    private function __construct(array $value)
    {
        $this->value = $value;
    }

    public static function fromArray(array $map): self
    {
        return new self($map);
    }

    public function getValue(): string
    {
        $pairs = [];
        foreach ($this->value as $key => $val) {
            $k = is_string($key) ? "'" . addslashes($key) . "'" : $key;
            $v = is_string($val) ? "'" . addslashes($val) . "'" : $val;
            $pairs[] = $k . ',' . $v;
        }
        return 'map(' . implode(',', $pairs) . ')';
    }

    public function __toString(): string
    {
        return (string) $this->getValue();
    }
}
