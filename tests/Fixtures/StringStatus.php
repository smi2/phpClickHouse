<?php

declare(strict_types=1);

namespace ClickHouseDB\Tests\Fixtures;

enum StringStatus: string
{
    case Active = 'active';
    case Quoted = "it's";
}
