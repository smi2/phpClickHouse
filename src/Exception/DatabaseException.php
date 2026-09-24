<?php

declare(strict_types=1);

namespace ClickHouseDB\Exception;

final class DatabaseException extends QueryException implements ClickHouseException
{
    private ?string $clickHouseExceptionName = null;
    private ?string $queryId                 = null;
    private ?string $serverVersion           = null;
    private ?string $serverStackTrace        = null;

    public static function fromClickHouse(
        string $message,
        int $code,
        ?string $exceptionName = null,
        ?string $queryId = null,
        ?string $serverVersion = null,
        ?string $serverStackTrace = null
    ): self {
        $exception                          = new self($message, $code);
        $exception->clickHouseExceptionName = $exceptionName;
        $exception->queryId                 = $queryId;
        $exception->serverVersion           = $serverVersion;
        $exception->serverStackTrace        = $serverStackTrace;

        return $exception;
    }

    public function getClickHouseExceptionName(): ?string
    {
        return $this->clickHouseExceptionName;
    }

    public function getQueryId(): ?string
    {
        return $this->queryId;
    }

    public function getServerVersion(): ?string
    {
        return $this->serverVersion;
    }

    /** Returns the server's stack frames, separate from PHP's local exception trace. */
    public function getServerStackTrace(): ?string
    {
        return $this->serverStackTrace;
    }
}
