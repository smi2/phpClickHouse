<?php

namespace ClickHouseDB\Transport;

/**
 * Class Stream
 * @package ClickHouseDB\Transport
 */
abstract class Stream implements IStream
{
    /**
     * @var mixed
     */
    private mixed $source;
    /**
     * @var bool
     */
    private bool $gzip=false;
    /**
     * @var null|callable
     */
    private mixed $callable=null;
    /**
     * @param mixed $source
     */
    public function __construct($source)
    {
        if (!is_resource($source)) {
            throw new \InvalidArgumentException('Argument $source must be resource');
        }
        $this->source = $source;
    }

    /**
     * @return bool
     */
    public function isGzipHeader(): bool
    {
        return $this->gzip;
    }

    /**
     * @return callable|null
     */
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

    /**
     * @param callable $callable
     */
    public function closure(callable $callable)
    {
        $this->callable=$callable;
    }

    /**
     *
     */
    public function enableGzipHeader()
    {
        $this->gzip=true;
    }

}
