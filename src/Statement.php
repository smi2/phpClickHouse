<?php

declare(strict_types=1);

namespace ClickHouseDB;

use ClickHouseDB\Exception\ClickHouseUnavailableException;
use ClickHouseDB\Exception\DatabaseException;
use ClickHouseDB\Exception\QueryException;
use ClickHouseDB\Query\Query;
use ClickHouseDB\Transport\CurlerRequest;
use ClickHouseDB\Transport\CurlerResponse;

class Statement implements \Iterator
{
    private const CLICKHOUSE_ERROR_REGEX = "%Code:\s(\d+)\.\s*DB::Exception\s*:\s*(.*)(?:,\s*e\.what|\(version).*%ius";
    private const CLICKHOUSE_EXCEPTION_NAME_REGEX = "%\(([A-Z_]+)\)\s*(?:\(version|$)%i";

    private mixed $_rawData = null;

    private int $_http_code = -1;

    private CurlerRequest $_request;

    private bool $_init = false;

    private mixed $query = null;

    private mixed $format = null;

    private string $sql = '';

    /** @var array|null */
    private ?array $meta = null;

    /** @var array|null */
    private ?array $totals = null;

    /** @var array|null */
    private ?array $extremes = null;

    private ?int $rows = null;

    private int|false $rows_before_limit_at_least = false;

    private array $array_data = [];

    private ?array $statistics = null;

    public int $iterator = 0;


    public function __construct(CurlerRequest $request)
    {
        $this->_request = $request;
        $this->format = $this->_request->getRequestExtendedInfo('format');
        $this->query = $this->_request->getRequestExtendedInfo('query');
        $sql = $this->_request->getRequestExtendedInfo('sql');
        $this->sql = is_string($sql) ? $sql : (string) ($sql ?: '');
    }

    public function getRequest(): CurlerRequest
    {
        return $this->_request;
    }

    /**
     * @throws Exception\TransportException
     */
    private function response(): CurlerResponse
    {
        return $this->_request->response();
    }

    /**
     * @throws Exception\TransportException
     */
    public function responseInfo(): array
    {
        return $this->response()->info();
    }

    public function sql(): string
    {
        return $this->sql;
    }

    /**
     * @return array|false
     */
    private function parseErrorClickHouse(string $body): array|false
    {
        $body = trim($body);
        $matches = [];

        if (preg_match(self::CLICKHOUSE_ERROR_REGEX, $body, $matches)) {
            $result = ['code' => $matches[1], 'message' => $matches[2], 'exception_name' => null];
            if (preg_match(self::CLICKHOUSE_EXCEPTION_NAME_REGEX, $body, $nameMatches)) {
                $result['exception_name'] = $nameMatches[1];
            }
            return $result;
        }

        return false;
    }

    private function hasErrorClickhouse(string $body, ?string $contentType): bool {
        if ($contentType === null || false === stripos($contentType, 'application/json')) {
            return preg_match(self::CLICKHOUSE_ERROR_REGEX, $body) === 1;
        }

        if (strlen($body) > 4096) {
            $tail = substr($body, -4096);
            return preg_match(self::CLICKHOUSE_ERROR_REGEX, $tail) === 1;
        }

        try {
            json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return true;
        }

        return false;
    }

    /**
     * @return false
     * @throws Exception\TransportException
     */
    public function error()
    {
        if (!$this->isError()) {
            return false;
        }

        $body = $this->response()->body();
        $error_no = $this->response()->error_no();
        $error = $this->response()->error();

        $dumpStatement = false;
        if (!$error_no && !$error) {
            $parse = $this->parseErrorClickHouse($body);

            if ($parse) {
                $queryId = $this->response()->headers('X-ClickHouse-Query-Id');
                throw DatabaseException::fromClickHouse(
                    $parse['message'] . "\nIN:" . $this->sql(),
                    (int) $parse['code'],
                    $parse['exception_name'] ?? null,
                    $queryId
                );
            } else {
                $code = $this->response()->http_code();
                $message = "HttpCode:" . $this->response()->http_code() . " ; " . $this->response()->error() . " ;" . $body;
                $dumpStatement = true;
            }
        } else {
            $code = $error_no;
            $message = $this->response()->error();
        }

        $exception = new QueryException($message, $code);
        if ($code === CURLE_COULDNT_CONNECT) {
            $exception = new ClickHouseUnavailableException($message, $code);
        }

        if ($dumpStatement) {
            $exception->setRequestDetails($this->_request->getDetails());
            $exception->setResponseDetails($this->response()->getDetails());
        }

        throw $exception;
    }

    /**
     * @throws Exception\TransportException
     */
    public function isError(): bool
    {
        if ($this->response()->http_code() !== 200) {
            return true;
        }

        if ($this->response()->error_no()) {
            return true;
        }

        if ($this->hasErrorClickhouse($this->response()->body(), $this->response()->content_type())) {
            return true;
        }

        return false;
    }

    private function check(): bool
    {
        if (!$this->_request->isResponseExists()) {
            throw QueryException::noResponse();
        }

        if ($this->isError()) {
            $this->error();
        }

        return true;
    }

    public function isInited(): bool
    {
        return $this->_init;
    }

    /**
     * @throws Exception\TransportException
     */
    private function init(): bool
    {
        if ($this->_init) {
            return false;
        }

        $this->check();

        $this->_rawData = $this->response()->rawDataOrJson($this->format);

        if (!$this->_rawData) {
            $this->_init = true;
            return false;
        }

        $data = [];
        foreach (['meta', 'data', 'totals', 'extremes', 'rows', 'rows_before_limit_at_least', 'statistics'] as $key) {

            if (isset($this->_rawData[$key])) {
                if ($key=='data') {
                    $data=$this->_rawData[$key];
                } else {
                    $this->{$key} = $this->_rawData[$key];
                }
            }
        }

        if (empty($this->meta)) {
            throw new QueryException('Can`t find meta');
        }

        $isJSONCompact=(stripos($this->format,'JSONCompact')!==false?true:false);
        $this->array_data = [];
        foreach ($data as $rows) {
            $r = [];

            if ($isJSONCompact) {
                $r[] = $rows;
            } else {
                foreach ($this->meta as $meta) {
                    $r[$meta['name']] = $rows[$meta['name']];
                }
            }

            $this->array_data[] = $r;
        }

        $this->_init = true;

        return true;
    }

    /**
     * @throws \Exception
     */
    public function extremes(): ?array
    {
        $this->init();
        return $this->extremes;
    }

    /**
     * @throws Exception\TransportException
     */
    public function totalTimeRequest(): float
    {
        $this->check();
        return $this->response()->total_time();
    }

    /**
     * @throws \Exception
     */
    public function extremesMin(): array
    {
        $this->init();

        if (empty($this->extremes['min'])) {
            return [];
        }

        return $this->extremes['min'];
    }

    /**
     * @throws \Exception
     */
    public function extremesMax(): array
    {
        $this->init();

        if (empty($this->extremes['max'])) {
            return [];
        }

        return $this->extremes['max'];
    }

    /**
     * @throws Exception\TransportException
     */
    public function totals(): ?array
    {
        $this->init();
        return $this->totals;
    }

    public function dump(): void
    {
        $this->_request->dump();
        $this->response()->dump();
    }

    /**
     * @throws Exception\TransportException
     */
    public function countAll(): int|false
    {
        $this->init();
        return $this->rows_before_limit_at_least;
    }

    /**
     * @param bool|string $key
     * @throws Exception\TransportException
     */
    public function statistics(mixed $key = false): mixed
    {
        $this->init();

        if (!is_array($this->statistics)) {
            return $this->summary($key);
        }

        if (!$key) return $this->statistics;

        if (!isset($this->statistics[$key])) {
            return null;
        }

        return $this->statistics[$key];
    }

    /**
     * Returns data from the X-ClickHouse-Summary response header.
     *
     * ClickHouse sends this header for INSERT/write queries with stats like
     * written_rows, written_bytes, etc.
     *
     * @param bool|string $key
     */
    public function summary(mixed $key = false): mixed
    {
        $raw = $this->response()->headers('X-ClickHouse-Summary');

        if (!$raw) {
            return null;
        }

        $summary = json_decode($raw, true);

        if (!is_array($summary)) {
            return null;
        }

        if (!$key) {
            return $summary;
        }

        return $summary[$key] ?? null;
    }

    /**
     * @throws Exception\TransportException
     */
    public function count(): int
    {
        $this->init();
        return $this->rows ?? 0;
    }

    /**
     * @throws Exception\TransportException
     */
    public function rawData(): mixed
    {
        if ($this->_init) {
            return $this->_rawData;
        }

        $this->check();

        return $this->response()->rawDataOrJson($this->format);
    }

    public function resetIterator(): void
    {
        $this->iterator = 0;
    }

    public function fetchRow(mixed $key = null): mixed
    {
        $this->init();

        $position=$this->iterator;

        if (!isset($this->array_data[$position])) {
            return null;
        }

        $this->iterator++;

        if (!$key) {
            return $this->array_data[$position];
        }
        if (!isset($this->array_data[$position][$key])) {
            return null;
        }

        return $this->array_data[$position][$key];
    }

    /**
     * @throws Exception\TransportException
     */
    public function fetchOne(mixed $key = null): mixed
    {
        $this->init();
        if (!isset($this->array_data[0])) {
            return null;
        }

        if (!$key) {
            return $this->array_data[0];
        }

        if (!isset($this->array_data[0][$key])) {
            return null;
        }

        return $this->array_data[0][$key];
    }

    /**
     * @param string|array|null $path
     * @throws Exception\TransportException
     */
    public function rowsAsTree($path): array
    {
        $this->init();

        $out = [];
        foreach ($this->array_data as $row) {
            $d = $this->array_to_tree($row, $path);
            $out = array_replace_recursive($d, $out);
        }

        return $out;
    }

    /**
     * Return size_upload,upload_content,speed_upload,time_request
     *
     * @throws Exception\TransportException
     */
    public function info_upload(): array
    {
        $this->check();
        return [
            'size_upload'    => $this->response()->size_upload(),
            'upload_content' => $this->response()->upload_content_length(),
            'speed_upload'   => $this->response()->speed_upload(),
            'time_request'   => $this->response()->total_time()
        ];
    }

    /**
     * Return size_upload,upload_content,speed_upload,time_request,starttransfer_time,size_download,speed_download
     *
     * @throws Exception\TransportException
     */
    public function info(): array
    {
        $this->check();
        return [
            'starttransfer_time'    => $this->response()->starttransfer_time(),
            'size_download'    => $this->response()->size_download(),
            'speed_download'    => $this->response()->speed_download(),
            'size_upload'    => $this->response()->size_upload(),
            'upload_content' => $this->response()->upload_content_length(),
            'speed_upload'   => $this->response()->speed_upload(),
            'time_request'   => $this->response()->total_time()
        ];
    }

    public function getFormat(): mixed
    {
        return $this->format;
    }

    /**
     * @throws Exception\TransportException
     */
    public function rows(): array
    {
        $this->init();
        return $this->array_data;
    }

    /**
     * Iterate over rows using a generator (memory-efficient for large resultsets).
     * Unlike rows(), this does not build the full array in memory.
     *
     * @throws Exception\TransportException
     */
    public function rowsGenerator(): \Generator
    {
        $this->init();
        foreach ($this->array_data as $key => $row) {
            yield $key => $row;
        }
    }

    public function jsonRows(): string|false
    {
        return json_encode($this->rows(), JSON_PRETTY_PRINT);
    }

    /**
     * @param array|string $arr
     * @param null|string|array $path
     */
    private function array_to_tree($arr, $path = null): array
    {
        if (is_array($path)) {
            $keys = $path;
        } else {
            $args = func_get_args();
            array_shift($args);

            if (sizeof($args) < 2) {
                $separator = '.';
                $keys = explode($separator, $path);
            } else {
                $keys = $args;
            }
        }

        $tree = $arr;
        while (count($keys)) {
            $key = array_pop($keys);

            if (isset($arr[$key])) {
                $val = $arr[$key];
            } else {
                $val = $key;
            }

            $tree = array($val => $tree);
        }
        if (!is_array($tree)) {
            return [];
        }
        return $tree;
    }


    public function rewind(): void {
        $this->iterator = 0;
    }

    public function current(): mixed {
        if (!isset($this->array_data[$this->iterator])) {
            return null;
        }
        return $this->array_data[$this->iterator];
    }

    public function key(): int {
        return $this->iterator;
    }

    public function next(): void {
        ++$this->iterator;
    }

    public function valid(): bool {
        $this->init();
        return isset($this->array_data[$this->iterator]);
    }
}
