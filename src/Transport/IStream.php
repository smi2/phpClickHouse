<?php

namespace ClickHouseDB\Transport;

/**
 * @package ClickHouseDB\Transport
 */
interface IStream
{
    public function isGzipHeader();
    public function closure(callable $callable);
    public function getStream();
    public function getClosure();
    public function isWrite();
    public function applyGzip();
}
