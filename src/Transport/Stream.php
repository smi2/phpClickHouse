<?php

namespace ClickHouseDB\Transport;

use InvalidArgumentException;

use function is_resource;

abstract class Stream implements IStream
{
    /** @var mixed */
    private mixed $source;
    /** @var bool */
    private bool $gzip = false;
    /** @var callable|null */
    private mixed $callable = null;

    /**
     * @param mixed $source
     */
    public function __construct($source)
    {
        if (! is_resource($source)) {
            throw new InvalidArgumentException('Argument $source must be resource');
        }

        $this->source = $source;
    }

    public function isGzipHeader(): bool
    {
        return $this->gzip;
    }

    public function getClosure(): ?callable
    {
        return $this->callable;
    }

    /**
     * @return mixed
     */
    public function getStream()
    {
        return $this->source;
    }

    public function closure(callable $callable)
    {
        $this->callable = $callable;
    }

    public function enableGzipHeader()
    {
        $this->gzip = true;
    }
}
