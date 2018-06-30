<?php

namespace ClickHouseDB\Transport;

/**
 * Class StreamWrite
 * @package ClickHouseDB\Transport
 */
class StreamWrite
{
    /**
     * @var resource
     */
    private $source;
    /**
     * @var bool
     */
    private $gzip=false;
    /**
     * @var null|callable
     */
    private $callable=null;
    /**
     * @param resource $source
     */
    public function __construct($source)
    {
        if (!is_resource($source)) {
            throw new \InvalidArgumentException('Argument $source must be resource');
        }
        $this->source = $source;
    }

    public function applyGzip()
    {
        stream_filter_append($this->source, 'zlib.deflate', STREAM_FILTER_READ, ['window' => 30]);
        $this->enableGzipHeader();
    }

    /**
     * @return bool
     */
    public function isGzipHeader()
    {
        return $this->gzip;
    }

    /**
     * @return callable|null
     */
    public function getClosure()
    {
        return $this->callable;
    }

    /**
     * @return resource
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
