<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

use Stringable;

final class Boolean implements Type, Stringable
{
    public string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function fromBool(bool $value): self
    {
        return new self($value ? '1' : '0');
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
