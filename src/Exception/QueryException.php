<?php

declare(strict_types=1);

namespace ClickHouseDB\Exception;

use LogicException;

class QueryException extends LogicException implements ClickHouseException
{
    protected $requestDetails = [];
    protected $responseDetails = [];

    public static function cannotInsertEmptyValues() : self
    {
        return new self('Inserting empty values array is not supported in ClickHouse');
    }

    public static function noResponse() : self
    {
        return new self('No response returned');
    }

    public function setRequestDetails(array $requestDetails)
    {
        $this->requestDetails = $requestDetails;
    }

    public function getRequestDetails(): array
    {
        return $this->requestDetails;
    }

    public function setResponseDetails(array $responseDetails)
    {
        $this->responseDetails = $responseDetails;
    }

    public function getResponseDetails(): array
    {
        return $this->responseDetails;
    }
}
