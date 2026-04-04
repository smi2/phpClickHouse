<?php

namespace ClickHouseDB\Transport;

use const CURLOPT_HTTPGET;
use const CURLOPT_POST;

class CurlerRequest
{
    public array $extendinfo = [];

    private array|string $parameters = '';

    private array $options = [];

    private array $headers = []; // Parsed response header object.

    private string $url = '';

    private string $method = '';

    private mixed $id = false;

    private \CurlHandle|false|null $handle = null;

    private ?CurlerResponse $response = null;

    private bool $_persistent = false;

    private bool $_attachFiles = false;

    private mixed $callback_class = '';

    private string $callback_functionName = '';

    private bool $_httpCompression = false;

    private mixed $callback_function = null;

    private mixed $infile_handle = false;

    private int $_dns_cache = 120;

    private mixed $resultFileHandle = null;

    private ?string $sslCa = null;

    private mixed $stdErrOut = null;
    public function __construct(mixed $id = false)
    {
        $this->id = $id;

        $this->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->header('Expires', '0');
        $this->header('Pragma', 'no-cache');

        $this->options = array(
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5, // Количество секунд ожидания при попытке соединения
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HEADER => TRUE,
            CURLOPT_FOLLOWLOCATION => TRUE,
            CURLOPT_AUTOREFERER => 1, // при редиректе подставлять в «Referer:» значение из «Location:»
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_USERAGENT => 'smi2/PHPClickHouse/client',
        );
    }

    public function __destruct()
    {
        $this->close();
    }


    public function close(): void
    {
        $this->handle = null;
    }

    public function attachFiles(array $attachFiles): void
    {
        $this->header("Content-Type", "multipart/form-data");

        $out = [];
        foreach ($attachFiles as $post_name => $file_path) {
            $out[$post_name] = new \CURLFile($file_path);
        }

        $this->_attachFiles = true;
        $this->parameters($out);
    }


    public function id(mixed $set = false): static
    {
        if ($set) {
            $this->id = $set;
        }

        return $this;
    }

    public function setRequestExtendedInfo(array $params): static
    {
        $this->extendinfo = $params;
        return $this;
    }

    public function getRequestExtendedInfo(mixed $key = null): mixed
    {
        if ($key) {
            return isset($this->extendinfo[$key]) ? $this->extendinfo[$key] : false;
        }

        return $this->extendinfo;
    }

    public function getInfileHandle(): mixed
    {
        return $this->infile_handle;
    }

    public function setInfile(string $file_name): mixed
    {
        $this->header('Expect', '');
        $this->infile_handle = fopen($file_name, 'r');
        if (is_resource($this->infile_handle)) {

            if ($this->_httpCompression) {
                $this->header('Content-Encoding', 'gzip');
                $this->header('Content-Type', 'application/x-www-form-urlencoded');

                stream_filter_append($this->infile_handle, 'zlib.deflate', STREAM_FILTER_READ, ["window" => 30]);

                $this->options[CURLOPT_SAFE_UPLOAD] = 1;
            } else {
                $this->options[CURLOPT_INFILESIZE] = filesize($file_name);
            }

            $this->options[CURLOPT_INFILE] = $this->infile_handle;
        }

        return $this->infile_handle;
    }

    public function setCallbackFunction(callable $callback): void
    {
        $this->callback_function = $callback;
    }

    public function setWriteFunction(callable $callback): void
    {
        $this->options[CURLOPT_WRITEFUNCTION] = $callback;
    }

    public function setReadFunction(callable $callback): void
    {
        $this->options[CURLOPT_READFUNCTION] = $callback;
    }

    public function setHeaderFunction(callable $callback): void
    {
        $this->options[CURLOPT_HEADERFUNCTION] = $callback;
    }

    public function setCallback(mixed $classCallBack, string $functionName): void
    {
        $this->callback_class = $classCallBack;
        $this->callback_functionName = $functionName;
    }

    public function onCallback(): void
    {
        if ($this->callback_function) {
            $x = $this->callback_function;
            $x($this);
        }

        if ($this->callback_class && $this->callback_functionName) {
            $c = $this->callback_functionName;
            $this->callback_class->$c($this);
        }
    }

    public function getDetails(): array
    {
        return [
            'url'        => $this->url,
            'method'     => $this->method,
            'parameters' => $this->parameters,
            'headers'    => $this->headers,
        ];
    }

    public function setStdErrOut(mixed $stream): void
    {
        if (is_resource($stream)) {
            $this->stdErrOut = $stream;
        }
    }

    public function dump(bool $result = false): string
    {
        $message = "\n------------  Request ------------\n";
        $message .= 'URL:' . $this->url . "\n\n";
        $message .= 'METHOD:' . $this->method . "\n\n";
        $message .= 'PARAMS:' . print_r($this->parameters, true) . "\n";
        $message .= 'PARAMS:' . print_r($this->headers, true) . "\n";
        $message .= "-----------------------------------\n";

        if ($result) {
            return $message;
        }

        echo $message;
        return '';
    }

    public function getId(): mixed
    {
        return $this->id;
    }

    public function option(int $key, mixed $value): static
    {
        $this->options[$key] = $value;
        return $this;
    }

    public function persistent(): static
    {
        $this->_persistent = true;
        return $this;
    }

    public function isPersistent(): bool
    {
        return $this->_persistent;
    }

    public function keepAlive(int $sec = 60): static
    {
        $this->options[CURLOPT_FORBID_REUSE] = TRUE;
        $this->headers['Connection'] = 'Keep-Alive';
        $this->headers['Keep-Alive'] = $sec;

        return $this;
    }

    public function verbose(bool $flag = true): static
    {
        $this->options[CURLOPT_VERBOSE] = $flag;
        return $this;
    }

    public function header(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function getHeaders():array
    {
        $head = [];
        foreach ($this->headers as $key => $value) {
            $head[] = sprintf("%s: %s", $key, $value);
        }
        return $head;
    }

    public function url(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getUrl():string
    {
        return $this->url;
    }


    public function getUniqHash(string $id):string
    {
        return $id . '.' . microtime() . mt_rand(0, 1000000);
    }

    public function httpCompression(bool $flag):void
    {
        if ($flag) {
            $this->_httpCompression = $flag;
            $this->options[CURLOPT_ENCODING] = 'gzip';
        } else {
            $this->_httpCompression = false;
            unset($this->options[CURLOPT_ENCODING]);
        }
    }

    public function authByBasicAuth(string $username, string $password): static
    {
        $this->options[CURLOPT_USERPWD] = sprintf("%s:%s", $username, $password);
        return $this;
    }

    public function authByHeaders(string $username, string $password): static
    {
        $this->headers['X-ClickHouse-User'] = $username;
        $this->headers['X-ClickHouse-Key'] = $password;
        return $this;
    }

    public function parameters(array|string $data): static
    {
        $this->parameters = $data;
        return $this;
    }

    /**
     * The number of seconds to wait when trying to connect. Use 0 for infinite waiting.
     *
     */
    public function connectTimeOut(float $seconds = 1.0): static
    {
        $this->options[CURLOPT_CONNECTTIMEOUT_MS] = (int) ($seconds*1000.0);
        return $this;
    }

    /**
     * The maximum number of seconds (float) allowed to execute cURL functions.
     *
     */
    public function timeOut(float $seconds = 10): static
    {
        return $this->timeOutMs((int) ($seconds * 1000.0));
    }

    /**
     * The maximum allowed number of milliseconds to perform cURL functions.
     *
     */
    protected function timeOutMs(int $ms = 10000): static
    {
        $this->options[CURLOPT_TIMEOUT_MS] = $ms;
        return $this;
    }


    /**
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function parameters_json(mixed $data): static
    {

        $this->header("Content-Type", "application/json, text/javascript; charset=utf-8");
        $this->header("Accept", "application/json, text/javascript, */*; q=0.01");

        if ($data === null) {
            $this->parameters = '{}';
            return $this;
        }

        if (is_string($data)) {
            $this->parameters = $data;
            return $this;
        }

        $this->parameters = json_encode($data);

        if (!$this->parameters && $data) {
            throw new \ClickHouseDB\Exception\TransportException('Cant json_encode: ' . strval($data));
        }

        return $this;
    }

    public function getResultFileHandle(): mixed
    {
        return $this->resultFileHandle;
    }

    public function isResultFile(): bool
    {
        return ($this->resultFileHandle ? true : false);
    }

    public function setResultFileHandle($h, bool $zlib = false): static
    {
        $this->resultFileHandle = $h;
        if ($zlib) {
            $params = array('level' => 6, 'window' => 15, 'memory' => 9);
            stream_filter_append($this->resultFileHandle, 'zlib.deflate', STREAM_FILTER_WRITE, $params);
        }
        return $this;
    }

    public function PUT(): static
    {
        return $this->execute('PUT');
    }

    public function POST(): static
    {
        return $this->execute('POST');
    }

    public function OPTIONS(): static
    {
        return $this->execute('OPTIONS');
    }

    public function GET(): static
    {
        return $this->execute('GET');
    }

    /**
     * The number of seconds that DNS records are stored in memory. By default this parameter is 120 (2 minutes).
     *
     */
    public function setDnsCache(int $set): static
    {
        $this->_dns_cache = $set;
        return $this;
    }

    /**
     * The number of seconds that DNS records are stored in memory. By default this parameter is 120 (2 minutes).
     *
     */
    public function getDnsCache(): int
    {
        return $this->_dns_cache;
    }

    /**
     * Sets client certificate
     *
     */
    public function setSslCa(string $filePath): void
    {
        $this->option(CURLOPT_SSL_VERIFYPEER, true);
        $this->option(CURLOPT_CAINFO, $filePath);
    }

    private function execute(string $method): static
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @throws \ClickHouseDB\Exception\TransportException
     */
    public function response(): CurlerResponse
    {
        if (!$this->response) {
            throw new \ClickHouseDB\Exception\TransportException('Can`t fetch response - is empty');
        }

        return $this->response;
    }

    public function isResponseExists(): bool
    {
        return $this->response !== null;
    }

    public function setResponse(CurlerResponse $response): void
    {
        $this->response = $response;
    }

    public function handle(): mixed
    {
        $this->prepareRequest();
        return $this->handle;
    }

    /**
     * @throws \Exception
     */
    public function setFunctionProgress(callable $callback): void
    {
        if (!is_callable($callback)) {
            throw new \Exception('setFunctionProgress not is_callable');
        }

        $this->option(CURLOPT_NOPROGRESS, false);
        $this->option(CURLOPT_PROGRESSFUNCTION, $callback); // version 5.5.0
    }


    private function prepareRequest(): bool
    {
        if (!$this->handle) {
            $this->handle = curl_init();
        }

        $curl_opt = $this->options;
        $method = $this->method;

        if ($this->_attachFiles) {
            $curl_opt[CURLOPT_SAFE_UPLOAD] = true;
        }


        if (strtoupper($method) == 'GET') {
            $curl_opt[CURLOPT_HTTPGET] = true;
            $curl_opt[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
            $curl_opt[CURLOPT_POSTFIELDS] = false;
        } else {
            if (strtoupper($method) === 'POST') {
                $curl_opt[CURLOPT_POST] = true;
            }

            $curl_opt[CURLOPT_CUSTOMREQUEST] = strtoupper($method);

            if ($this->parameters) {
                $curl_opt[CURLOPT_POSTFIELDS] = $this->parameters;

                if (!is_array($this->parameters)) {
                    $this->header('Content-Length',  mb_strlen($this->parameters, '8bit'));
                }
            }
        }
        // CURLOPT_DNS_CACHE_TIMEOUT - Количество секунд, в течение которых в памяти хранятся DNS-записи.
        $curl_opt[CURLOPT_DNS_CACHE_TIMEOUT] = $this->getDnsCache();
        $curl_opt[CURLOPT_URL] = $this->url;

        if (!empty($this->headers) && sizeof($this->headers)) {
            $curl_opt[CURLOPT_HTTPHEADER] = [];

            foreach ($this->headers as $key => $value) {
                $curl_opt[CURLOPT_HTTPHEADER][] = sprintf("%s: %s", $key, $value);
            }
        }

        if (!empty($curl_opt[CURLOPT_INFILE])) {

            $curl_opt[CURLOPT_PUT] = true;
        }

        if (!empty($curl_opt[CURLOPT_WRITEFUNCTION])) {
            $curl_opt[CURLOPT_HEADER] = false;
        }

        if ($this->resultFileHandle) {
            $curl_opt[CURLOPT_FILE] = $this->resultFileHandle;
            $curl_opt[CURLOPT_HEADER] = false;
        }

        if ($this->options[CURLOPT_VERBOSE]) {
            $msg="\n-----------BODY REQUEST----------\n" . $curl_opt[CURLOPT_POSTFIELDS] . "\n------END--------\n";
            if ($this->stdErrOut && is_resource($this->stdErrOut)) {
                fwrite($this->stdErrOut,$msg);
            } else {
                echo $msg;
            }
        }

        if ($this->stdErrOut) {
            if (is_resource($this->stdErrOut)) {
                $curl_opt[CURLOPT_STDERR]=$this->stdErrOut;
            }
        }

        curl_setopt_array($this->handle, $curl_opt);
        return true;
    }
}
