<?php

namespace ClickHouseDB\Transport;

use function stream_filter_append;

use const STREAM_FILTER_READ;

class StreamWrite extends Stream
{
    public function __construct($source)
    {
        parent::__construct($source);
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function applyGzip()
    {
        stream_filter_append($this->getStream(), 'zlib.deflate', STREAM_FILTER_READ, ['window' => 30]);
        $this->enableGzipHeader();
    }
}
