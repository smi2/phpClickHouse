<?php

declare(strict_types=1);

namespace ClickHouseDB;

use ClickHouseDB\Exception\QueryException;
use ClickHouseDB\Exception\TransportException;
use ClickHouseDB\Query\Degeneration;
use ClickHouseDB\Query\Degeneration\Bindings;
use ClickHouseDB\Query\Degeneration\Conditions;
use ClickHouseDB\Query\WhereInFile;
use ClickHouseDB\Query\WriteToFile;
use ClickHouseDB\Quote\FormatLine;
use ClickHouseDB\Transport\Http;
use ClickHouseDB\Transport\Stream;
use function array_flip;
use function array_keys;
use function array_rand;
use function array_values;
use function count;
use function date;
use function implode;
use function in_array;
use function is_array;
use function is_callable;
use function is_file;
use function is_readable;
use function is_string;
use function sprintf;
use function stripos;
use function strtotime;
use function trim;

class Client
{
    const SUPPORTED_FORMATS = ['TabSeparated', 'TabSeparatedWithNames', 'CSV', 'CSVWithNames', 'JSONEachRow','CSVWithNamesAndTypes','TSVWithNamesAndTypes'];

    private Http $transport;

    private string $connectUsername;

    private string $connectPassword;

    private string $connectHost;

    private int $connectPort;

    private ?int $authMethod = null;

    private bool $connectUserReadonly = false;

    public function __construct(array $connectParams, array $settings = [])
    {
        if (!isset($connectParams['username'])) {
            throw new \InvalidArgumentException('not set username');
        }

        if (!isset($connectParams['password'])) {
            throw new \InvalidArgumentException('not set password');
        }

        if (!isset($connectParams['port'])) {
            throw new \InvalidArgumentException('not set port');
        }

        if (!isset($connectParams['host'])) {
            throw new \InvalidArgumentException('not set host');
        }

        if (array_key_exists('auth_method', $connectParams)) {
            if (false === in_array($connectParams['auth_method'], Http::AUTH_METHODS_LIST)) {
                $errorMessage = sprintf(
                    'Invalid value for "auth_method" param. Should be one of [%s].',
                    json_encode(Http::AUTH_METHODS_LIST)
                );
                throw new \InvalidArgumentException($errorMessage);
            }

            $this->authMethod = $connectParams['auth_method'];
        }

        $this->connectUsername = $connectParams['username'];
        $this->connectPassword = $connectParams['password'];
        $this->connectPort = intval($connectParams['port']);
        $this->connectHost = $connectParams['host'];

        // init transport class
        $this->transport = new Http(
            $this->connectHost,
            $this->connectPort,
            $this->connectUsername,
            $this->connectPassword,
            $this->authMethod
        );

        $this->transport->addQueryDegeneration(new Bindings());

        // apply settings to transport class
        $this->settings()->database('default');
        if (!empty($settings)) {
            $this->settings()->apply($settings);
        }

        if (isset($connectParams['readonly'])) {
            $this->setReadOnlyUser($connectParams['readonly']);
        }

        if (isset($connectParams['https'])) {
            $this->https($connectParams['https']);
        }

        if (isset($connectParams['sslCA'])) {
            $this->transport->setSslCa($connectParams['sslCA']);
        }

        if (isset($connectParams['curl_options']) && is_array($connectParams['curl_options'])) {
            $this->transport->setCurlOptions($connectParams['curl_options']);
        }
    }

    /**
     * if the user has only read in the config file
     */
    public function setReadOnlyUser(bool $flag): void
    {
        $this->connectUserReadonly = $flag;
        $this->settings()->setReadOnlyUser($this->connectUserReadonly);
    }

    /**
     * Clear Degeneration processing request [template ]
     *
     */
    public function cleanQueryDegeneration(): bool
    {
        return $this->transport->cleanQueryDegeneration();
    }

    /**
     * add Degeneration processing
     *
     */
    public function addQueryDegeneration(Degeneration $degeneration): bool
    {
        return $this->transport->addQueryDegeneration($degeneration);
    }

    /**
     * add Conditions in query
     *
     */
    public function enableQueryConditions(): bool
    {
        return $this->transport->addQueryDegeneration(new Conditions());
    }

    /**
     * Set connection host
     *
     */
    public function setHost(string $host): void
    {
        $this->connectHost = $host;
        $this->transport()->setHost($host);
    }

    /**
     * max_execution_time , in int value (seconds)
     */
    public function setTimeout(mixed $timeout): Settings
    {
        return $this->settings()->max_execution_time(intval($timeout));
    }

    public function getTimeout(): int
    {
        return $this->settings()->getTimeOut();
    }

    /**
     * ConnectTimeOut in seconds ( support 1.5 = 1500ms )
     */
    public function setConnectTimeOut(float $connectTimeOut): void
    {
        $this->transport()->setConnectTimeOut($connectTimeOut);
    }

    public function getConnectTimeOut(): float
    {
        return $this->transport()->getConnectTimeOut();
    }

    public function transport(): Http
    {
        if (!$this->transport) {
            throw new \InvalidArgumentException('Empty transport class');
        }

        return $this->transport;
    }

    public function getConnectHost(): string
    {
        return $this->connectHost;
    }

    public function getConnectPassword(): string
    {
        return $this->connectPassword;
    }

    public function getConnectPort(): string
    {
        return strval($this->connectPort);
    }

    public function getConnectUsername(): string
    {
        return $this->connectUsername;
    }

    public function getAuthMethod(): int
    {
        return $this->authMethod;
    }

    public function getTransport(): Http
    {
        return $this->transport;
    }

    public function verbose(bool $flag = true):bool
    {
        return $this->transport()->verbose(true);
    }

    public function settings(): Settings
    {
        return $this->transport()->settings();
    }

    public function useSession(string $useSessionId = ''): static
    {
        if (!$this->settings()->getSessionId()) {
            if (!$useSessionId) {
                $this->settings()->makeSessionId();
            } else {
                $this->settings()->session_id($useSessionId);
            }
        }
        return $this;
    }

    public function getSession(): string|false
    {
        return $this->settings()->getSessionId();
    }

    /**
     * Query CREATE/DROP
     *
     */
    public function write(string $sql, array $bindings = [], bool $exception = true, array $querySettings = []): Statement
    {
        return $this->transport()->write($sql, $bindings, $exception, $querySettings);
    }

    /**
     * set db name
     */
    public function database(string $db): static
    {
        $this->settings()->database($db);

        return $this;
    }

    /**
     * Write to system.query_log
     *
     */
    public function enableLogQueries(bool $flag = true): static
    {
        $this->settings()->set('log_queries', (int)$flag);

        return $this;
    }

    /**
     * Compress the result if the HTTP client said that it understands data compressed with gzip or deflate
     *
     */
    public function enableHttpCompression(bool $flag = true): static
    {
        $this->settings()->enableHttpCompression($flag);

        return $this;
    }

    /**
     * Enable / Disable HTTPS
     *
     */
    public function https(bool $flag = true): static
    {
        $this->settings()->https($flag);

        return $this;
    }

    /**
     * Read extremes of the result columns. They can be output in JSON-formats.
     *
     */
    public function enableExtremes(bool $flag = true): static
    {
        $this->settings()->set('extremes', (int)$flag);

        return $this;
    }

    public function select(
        string $sql,
        array $bindings = [],
        ?WhereInFile $whereInFile = null,
        ?WriteToFile $writeToFile = null,
        array $querySettings = []
    ): Statement
    {
        return $this->transport()->select($sql, $bindings, $whereInFile, $writeToFile, $querySettings);
    }

    public function executeAsync(): bool
    {
        return $this->transport()->executeAsync();
    }

    public function maxTimeExecutionAllAsync(): void
    {

    }

    /**
     * set progressFunction
     */
    public function progressFunction(callable $callback): void
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Not is_callable progressFunction');
        }

        if (!$this->settings()->is('send_progress_in_http_headers')) {
            $this->settings()->set('send_progress_in_http_headers', 1);
        }
        if (!$this->settings()->is('http_headers_progress_interval_ms')) {
            $this->settings()->set('http_headers_progress_interval_ms', 100);
        }
        // Required for write operations to receive progress headers
        if (!$this->settings()->is('wait_end_of_query')) {
            $this->settings()->set('wait_end_of_query', 1);
        }

        $this->transport()->setProgressFunction($callback);
    }

    /**
     * prepare select
     *
     */
    public function selectAsync(
        string $sql,
        array $bindings = [],
        ?WhereInFile $whereInFile = null,
        ?WriteToFile $writeToFile = null,
        array $querySettings = []
    ): Statement
    {
        return $this->transport()->selectAsync($sql, $bindings, $whereInFile, $writeToFile, $querySettings);
    }

    /**
     * Execute SELECT with native ClickHouse typed parameters.
     *
     * Uses server-side parameter binding: {name:Type} in SQL + param_name in URL.
     * This is the safest way to pass parameters — SQL injection is impossible at protocol level.
     *
     */
    public function selectWithParams(string $sql, array $params, array $querySettings = []): Statement
    {
        return $this->transport()->selectWithParams($sql, $params, $querySettings);
    }

    /**
     * Execute write (DDL/DML) with native ClickHouse typed parameters.
     *
     */
    public function writeWithParams(string $sql, array $params, bool $exception = true, array $querySettings = []): Statement
    {
        return $this->transport()->writeWithParams($sql, $params, $exception, $querySettings);
    }

    /**
     * Memory-efficient SELECT using a generator.
     *
     * Streams results from ClickHouse using JSONEachRow format and yields
     * one row at a time. Unlike select()->rows(), this does not load
     * the entire resultset into memory.
     *
     */
    public function selectGenerator(string $sql, array $bindings = [], array $querySettings = []): \Generator
    {
        $stream = fopen('php://temp', 'r+');
        $streamRead = new Transport\StreamRead($stream);

        $this->transport()->streamRead($streamRead, $sql . ' FORMAT JSONEachRow', $bindings, $querySettings);

        rewind($stream);

        while (($line = fgets($stream)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (is_array($row)) {
                yield $row;
            }
        }

        fclose($stream);
    }

    /**
     * SHOW PROCESSLIST
     *
     */
    public function showProcesslist(): array
    {
        return $this->select('SHOW PROCESSLIST')->rows();
    }

    /**
     * show databases
     *
     */
    public function showDatabases(): array
    {
        return $this->select('show databases')->rows();
    }

    /**
     * statement = SHOW CREATE TABLE
     *
     */
    public function showCreateTable(string $table): mixed
    {
        return $this->select('SHOW CREATE TABLE ' . $table)->fetchOne('statement');
    }

    /**
     * SHOW TABLES
     *
     */
    public function showTables(): array
    {
        return $this->select('SHOW TABLES')->rowsAsTree('name');
    }

    /**
     * Get the number of simultaneous/Pending requests
     *
     */
    public function getCountPendingQueue(): int
    {
        return $this->transport()->getCountPendingQueue();
    }

    /**
     * @throws Exception\TransportException
     */
    public function insert(string $table, array $values, array $columns = []): Statement
    {
        if (empty($values)) {
            throw QueryException::cannotInsertEmptyValues();
        }

        if (stripos($table, '`') === false && stripos($table, '.') === false) {
            $table = '`' . $table . '`'; //quote table name for dot names
        }
        $sql = 'INSERT INTO ' . $table;

        if (count($columns) !== 0) {
            $sql .= ' (`' . implode('`,`', $columns) . '`) ';
        }

        $sql .= ' VALUES ';

        foreach ($values as $row) {
            $sql .= ' (' . FormatLine::Insert($row) . '), ';
        }
        $sql = trim($sql, ', ');

        return $this->transport()->write($sql);
    }

    /**
     * Prepares the values to insert from the associative array.
     * There may be one or more lines inserted, but then the keys inside the array list must match (including in the sequence)
     *
     **/
    public function prepareInsertAssocBulk(array $values): array
    {
        if (isset($values[0]) && is_array($values[0])) { //случай, когда много строк вставляется
            $preparedFields = array_keys($values[0]);
            $preparedValues = [];
            foreach ($values as $idx => $row) {
                $_fields = array_keys($row);
                if ($_fields !== $preparedFields) {
                    throw new QueryException(
                        sprintf(
                            'Fields not match: %s and %s on element %s',
                            implode(',', $_fields),
                            implode(',', $preparedFields),
                            $idx
                        )
                    );
                }
                $preparedValues[] = array_values($row);
            }
        } else {
            $preparedFields = array_keys($values);
            $preparedValues = [array_values($values)];
        }

        return [$preparedFields, $preparedValues];
    }

    /**
     * Inserts one or more rows from an associative array.
     * If there is a discrepancy between the keys of the value arrays (or their order) - throws an exception.
     *
     */
    public function insertAssocBulk(string $tableName, array $values): Statement
    {
        list($columns, $vals) = $this->prepareInsertAssocBulk($values);

        return $this->insert($tableName, $vals, $columns);
    }

    /**
     * insert TabSeparated files
     *
     */
    public function insertBatchTSVFiles(string $tableName, array|string $fileNames, array $columns = []): array
    {
        return $this->insertBatchFiles($tableName, $fileNames, $columns, 'TabSeparated');
    }

    /**
     * insert Batch Files
     *
     * @throws Exception\TransportException
     */
    public function insertBatchFiles(string $tableName, array|string $fileNames, array $columns = [], string $format = 'CSV'): array
    {
        if (is_string($fileNames)) {
            $fileNames = [$fileNames];
        }
        if ($this->getCountPendingQueue() > 0) {
            throw new QueryException('Queue must be empty, before insertBatch, need executeAsync');
        }

        if (!in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw new QueryException('Format not support in insertBatchFiles');
        }

        $result = [];

        foreach ($fileNames as $fileName) {
            if (!is_file($fileName) || !is_readable($fileName)) {
                throw new QueryException('Cant read file: ' . $fileName . ' ' . (is_file($fileName) ? '' : ' is not file'));
            }

            if (empty($columns)) {
                $sql = 'INSERT INTO ' . $tableName . ' FORMAT ' . $format;
            } else {
                $sql = 'INSERT INTO ' . $tableName . ' ( ' . implode(',', $columns) . ' ) FORMAT ' . $format;
            }
            $result[$fileName] = $this->transport()->writeAsyncCSV($sql, $fileName);
        }

        // exec
        $this->executeAsync();

        // fetch resutl
        foreach ($fileNames as $fileName) {
            if (!$result[$fileName]->isError()) {
                continue;
            }

            $result[$fileName]->error();
        }

        return $result;
    }

    /**
     * insert Batch Stream
     *
     */
    public function insertBatchStream(string $tableName, array $columns = [], string $format = 'CSV'): Transport\CurlerRequest
    {
        if ($this->getCountPendingQueue() > 0) {
            throw new QueryException('Queue must be empty, before insertBatch, need executeAsync');
        }

        if (!in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw new QueryException('Format not support in insertBatchFiles');
        }

        if (empty($columns)) {
            $sql = 'INSERT INTO ' . $tableName . ' FORMAT ' . $format;
        } else {
            $sql = 'INSERT INTO ' . $tableName . ' ( ' . implode(',', $columns) . ' ) FORMAT ' . $format;
        }

        return $this->transport()->writeStreamData($sql);
    }

    /**
     * stream Write
     *
     * @throws Exception\TransportException
     */
    public function streamWrite(Stream $stream, string $sql, array $bind = []): Statement
    {
        if ($this->getCountPendingQueue() > 0) {
            throw new QueryException('Queue must be empty, before streamWrite');
        }

        return $this->transport()->streamWrite($stream, $sql, $bind);
    }

    /**
     * stream Read
     *
     */
    public function streamRead(Stream $streamRead, string $sql, array $bind = []): Statement
    {
        if ($this->getCountPendingQueue() > 0) {
            throw new QueryException('Queue must be empty, before streamRead');
        }

        return $this->transport()->streamRead($streamRead, $sql, $bind);
    }

    /**
     * Size of database
     *
     * @throws \Exception
     */
    public function databaseSize(): mixed
    {
        $b = $this->settings()->getDatabase();

        return $this->select(
            '
            SELECT database,formatReadableSize(sum(bytes)) as size
            FROM system.parts
            WHERE active AND database=:database
            GROUP BY database
            ',
            ['database' => $b]
        )->fetchOne();
    }

    /**
     * Size of tables
     *
     * @throws \Exception
     */
    public function tableSize(string $tableName): mixed
    {
        $tables = $this->tablesSize();

        if (isset($tables[$tableName])) {
            return $tables[$tableName];
        }

        return null;
    }

    /**
     * Ping server
     *
     * @throws TransportException
     */
    public function ping(bool $throwException=false): bool
    {
        $result=$this->transport()->ping();
        if ($throwException && !$result) throw new TransportException('Can`t ping server');
        return $result;
    }

    /**
     * Tables sizes
     *
     * @throws \Exception
     */
    public function tablesSize(bool $flatList = false): array
    {
        $result = $this->select('
        SELECT name as table,database,
            max(sizebytes) as sizebytes,
            max(size) as size,
            min(min_date) as min_date,
            max(max_date) as max_date
            FROM system.tables
            ANY LEFT JOIN 
            (
            SELECT table,database,
                        formatReadableSize(sum(bytes)) as size,
                        sum(bytes) as sizebytes,
                        min(min_date) as min_date,
                        max(max_date) as max_date
                        FROM system.parts 
                        WHERE active AND database=:database
                        GROUP BY table,database
            ) as s USING ( table,database )
            WHERE database=:database
            GROUP BY table,database
        ',
            ['database' => $this->settings()->getDatabase()]);

        if ($flatList) {
            return $result->rows();
        }

        return $result->rowsAsTree('table');
    }

    /**
     * isExists
     *
     * @throws \Exception
     */
    public function isExists(string $database, string $table): array
    {
        return $this->select(
            '
            SELECT *
            FROM system.tables 
            WHERE name=\'' . $table . '\' AND database=\'' . $database . '\''
        )->rowsAsTree('name');
    }

    /**
     * List of partitions
     *
     * @throws \Exception
     */
    public function partitions(string $table, int $limit = 0, ?bool $active = null): array
    {
        $database = $this->settings()->getDatabase();
        $whereActiveClause = $active === null ? '' : sprintf(' AND active = %s', (int)$active);
        $limitClause = $limit > 0 ? ' LIMIT ' . $limit : '';

        return $this->select(<<<CLICKHOUSE
SELECT *
FROM system.parts 
WHERE table={tbl:String} AND database = {db:String}
$whereActiveClause
ORDER BY max_date $limitClause
CLICKHOUSE,
            [
                'db'=>$database,
                'tbl'=>$table
            ]
        )->rowsAsTree('name');
    }

    /**
     * dropPartition
     * @deprecated
     */
    public function dropPartition(string $dataBaseTableName, string $partition_id): Statement
    {

        $partition_id = trim($partition_id, '\'');
        $this->settings()->set('replication_alter_partitions_sync', 2);
        $state = $this->write('ALTER TABLE {dataBaseTableName} DROP PARTITION :partion_id',
            [
                'dataBaseTableName' => $dataBaseTableName,
                'partion_id' => $partition_id,
            ]);

        return $state;
    }

    /**
     * Truncate ( drop all partitions )
     * @throws \Exception
     * @deprecated
     */
    public function truncateTable(string $tableName): array
    {
        $partions = $this->partitions($tableName);
        $out = [];
        foreach ($partions as $part_key => $part) {
            $part_id = $part['partition'];
            $out[$part_id] = $this->dropPartition($tableName, $part_id);
        }

        return $out;
    }

    /**
     * Returns the server's uptime in seconds.
     *
     * @throws Exception\TransportException
     * @throws \Exception
     */
    public function getServerUptime(): mixed
    {
        return $this->select('SELECT uptime() as uptime')->fetchOne('uptime');
    }

    /**
     * Returns string with the server version.
     */
    public function getServerVersion(): string
    {
        return (string)$this->select('SELECT version() as version')->fetchOne('version');
    }

    /**
     * Read system.settings table
     *
     * @throws \Exception
     */
    public function getServerSystemSettings(string $like = ''): array
    {
        $l = [];
        $list = $this->select('SELECT * FROM system.settings' . ($like ? ' WHERE name LIKE :like' : ''),
            ['like' => '%' . $like . '%'])->rows();
        foreach ($list as $row) {
            if (isset($row['name'])) {
                $n = $row['name'];
                unset($row['name']);
                $l[$n] = $row;
            }
        }

        return $l;
    }

}
