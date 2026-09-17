<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

final class UInt256 implements ScalarNumericType
{
    public string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
