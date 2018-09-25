<?php

declare(strict_types=1);

namespace ClickHouseDB\Type;

interface Type
{
    /**
     * @return mixed
     */
    public function getValue();
}
