<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

final class TupleType implements Type
{
    public array $value;

    private function __construct(array $value)
    {
        $this->value = $value;
    }

    public static function fromArray(array $elements): self
    {
        return new self($elements);
    }

    public function getValue(): string
    {
        $parts = [];
        foreach ($this->value as $val) {
            if (is_string($val)) {
                $parts[] = "'" . addslashes($val) . "'";
            } elseif ($val === null) {
                $parts[] = 'NULL';
            } elseif (is_bool($val)) {
                $parts[] = $val ? '1' : '0';
            } else {
                $parts[] = $val;
            }
        }
        return '(' . implode(',', $parts) . ')';
    }

    public function __toString(): string
    {
        return $this->getValue();
    }
}
