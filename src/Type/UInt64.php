<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

final class UInt64 implements NumericType
{
    public string $value;

    private function __construct(string $uint64Value)
    {
        $this->value = $uint64Value;
    }

    public static function fromString(string $uint64Value): self
    {
        return new self($uint64Value);
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
