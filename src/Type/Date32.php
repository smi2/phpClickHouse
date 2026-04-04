<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

use DateTimeInterface;

final class Date32 implements Type
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

    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        return new self($dateTime->format('Y-m-d'));
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
