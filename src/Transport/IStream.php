<?php

namespace ClickHouseDB\Transport;

/**
 * @package ClickHouseDB\Transport
 */
interface IStream
{
    public function isGzipHeader(): bool;
    public function closure(callable $callable): void;
    public function getStream();
    public function getClosure(): ?callable;
    public function isWrite(): bool;
    public function applyGzip(): void;
}
