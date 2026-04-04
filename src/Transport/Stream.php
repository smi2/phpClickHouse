<?php

namespace ClickHouseDB\Transport;

/**
 * Class Stream
 * @package ClickHouseDB\Transport
 */
abstract class Stream implements IStream
{
    private $source;
    private bool $gzip = false;
    private mixed $callable = null;
    public function __construct($source)
    {
        if (!is_resource($source)) {
            throw new \InvalidArgumentException('Argument $source must be resource');
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

    public function getStream()
    {
        return $this->source;
    }

    public function closure(callable $callable): void
    {
        $this->callable=$callable;
    }

    public function enableGzipHeader(): void
    {
        $this->gzip=true;
    }

}
