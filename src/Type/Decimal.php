<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

final class Decimal implements NumericType
{
    /** @var string */
    public $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getValue()
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
