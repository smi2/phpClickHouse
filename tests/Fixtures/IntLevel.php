<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\Fixtures;

enum IntLevel: int
{
    case Low = 1;
    case High = 3;
}
