<?php

declare(strict_types=1);

namespace ClickHouseDB\Exception;

final class DatabaseException extends QueryException implements ClickHouseException
{
    private ?string $clickHouseExceptionName = null;
    private ?string $queryId = null;

    public static function fromClickHouse(
        string $message,
        int $code,
        ?string $exceptionName = null,
        ?string $queryId = null
    ): self {
        $exception = new self($message, $code);
        $exception->clickHouseExceptionName = $exceptionName;
        $exception->queryId = $queryId;
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
}
