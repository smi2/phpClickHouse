<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

use Stringable;

final class Decimal implements NumericType, Stringable
{
    public string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value, ?int $precision = null, ?int $scale = null): self
    {
        if (($precision === null) !== ($scale === null)) {
            throw new \InvalidArgumentException('Decimal precision and scale must be supplied together.');
        }

        return new self($value);
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
