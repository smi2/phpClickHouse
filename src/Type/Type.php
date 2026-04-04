<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

interface Type
{
    public function getValue(): mixed;
}
