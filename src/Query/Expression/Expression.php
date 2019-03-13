<?php

declare(strict_types=1);

namespace ClickHouseDB\Query\Expression;

interface Expression
{
    public function needsEncoding() : bool;
    public function getValue() : string;
}
