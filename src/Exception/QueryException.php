<?php

declare(strict_types=1);

namespace ClickHouseDB\Exception;

use LogicException;

class QueryException extends LogicException implements ClickHouseException
{
    protected array $requestDetails = [];
    protected array $responseDetails = [];

    public static function cannotInsertEmptyValues() : self
    {
        return new self('Inserting empty values array is not supported in ClickHouse');
    }

    public static function noResponse() : self
    {
        return new self('No response returned');
    }

    public function setRequestDetails(array $requestDetails): void
    {
        $this->requestDetails = $requestDetails;
    }

    public function getRequestDetails(): array
    {
        return $this->requestDetails;
    }

    public function setResponseDetails(array $responseDetails): void
    {
        $this->responseDetails = $responseDetails;
    }

    public function getResponseDetails(): array
    {
        return $this->responseDetails;
    }
}
