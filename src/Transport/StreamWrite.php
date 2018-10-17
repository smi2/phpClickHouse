<?php

namespace ClickHouseDB\Transport;

/**
 * Class StreamWrite
 * @package ClickHouseDB\Transport
 */
class StreamWrite extends Stream
{

    public function __construct( $source)
    {

        parent::__construct($source);

    }

    public function isWrite()
    {
        return true;
    }
    public function applyGzip()
    {
        stream_filter_append($this->getStream(), 'zlib.deflate', STREAM_FILTER_READ, ['window' => 30]);
        $this->enableGzipHeader();
    }
}
