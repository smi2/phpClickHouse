<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

use DateTimeInterface;

final class DateTime64 implements Type
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

    public static function fromDateTime(DateTimeInterface $dateTime, int $precision = 3): self
    {
        $formatted = $dateTime->format('Y-m-d H:i:s.u');
        $dotPos = strpos($formatted, '.');
        if ($dotPos !== false && $precision > 0) {
            $formatted = substr($formatted, 0, $dotPos + 1 + $precision);
        } elseif ($precision === 0) {
            $formatted = $dateTime->format('Y-m-d H:i:s');
        }
        return new self($formatted);
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
