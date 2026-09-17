<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Stringable;

use function substr;

final class DateTime64 implements DateType, Stringable
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

    public static function fromDateTime(DateTimeInterface $dateTime, int $precision = 3, ?string $timezone = null): self
    {
        if ($precision < 0 || $precision > 9) {
            throw new InvalidArgumentException('DateTime64 precision must be between 0 and 9.');
        }

        if ($timezone !== null) {
            $dateTime = DateTimeImmutable::createFromInterface($dateTime)->setTimezone(new DateTimeZone($timezone));
        }

        $formatted = $dateTime->format('Y-m-d H:i:s');
        if ($precision > 0) {
            $formatted .= '.' . substr($dateTime->format('u') . '000', 0, $precision);
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
