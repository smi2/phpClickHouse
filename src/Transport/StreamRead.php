<?php

namespace ClickHouseDB\Transport;

/**
 * Class StreamRead
 * @package ClickHouseDB\Transport
 */
class StreamRead extends Stream
{
    public function isWrite()
    {
        return false;
    }
    public function applyGzip()
    {
//        stream_filter_append($this->source, 'zlib.deflate', STREAM_FILTER_READ, ['window' => 30]);
        $this->enableGzipHeader();
    }
}
