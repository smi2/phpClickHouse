<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Stringable;

final class DateTime implements DateType, Stringable
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

    public static function fromDateTime(DateTimeInterface $dateTime, ?string $timezone = null): self
    {
        if ($timezone !== null) {
            $dateTime = DateTimeImmutable::createFromInterface($dateTime)->setTimezone(new DateTimeZone($timezone));
        }

        return new self($dateTime->format('Y-m-d H:i:s'));
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
