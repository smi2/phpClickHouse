<?php

namespace ClickHouseDB\Transport;

class CurlerResponse
{
    /**
     * @var array
     */
    public array $_headers = [];

    /**
     * @var array
     */
    public array $_info = [];

    /**
     * @var string
     */
    public string $_error = '';

    /**
     * @var int
     */
    public int $_errorNo = 0;

    /**
     * @var float
     */
    public float $_useTime = 0.0;

    /**
     * @var string
     */
    public string $_body = '';


    /**
     * Response constructor.
     */
    public function __construct() {}


    /**
     * @return int
     */
    public function error_no(): int
    {
        return $this->_errorNo;
    }

    /**
     * @return mixed
     */
    public function error()
    {
        return $this->_error;
    }

    /**
     * @return string
     */
    public function url(): string
    {
        return $this->_info['url'];
    }

    /**
     * @return float
     */
    public function total_time(): float
    {
        return round($this->_info['total_time'], 3);
    }

    /**
     * @return float
     */
    public function starttransfer_time(): float
    {
        return round($this->_info['starttransfer_time'], 3);
    }

    /**
     * @return float
     */
    public function connect_time(): float
    {
        return round($this->_info['connect_time'], 3);
    }

    /**
     * @return float
     */
    public function pretransfer_time(): float
    {
        return round($this->_info['pretransfer_time'], 3);
    }

    /**
     * @return string|null
     */
    public function content_type(): ?string
    {
        return $this->_info['content_type'];
    }

    /**
     * @return int
     */
    public function http_code(): int
    {
        return $this->_info['http_code'];
    }

    /**
     * @param string $name
     * @return null|string
     */
    public function headers(string $name): ?string
    {
        if (isset($this->_headers[$name])) {
            return $this->_headers[$name];
        }

        return null;
    }

    /**
     * @return null|string
     */
    public function connection(): ?string
    {
        return $this->headers('Connection');
    }

    /**
     * @return string
     */
    public function body(): string
    {
        return $this->_body;
    }

    /**
     * @return string
     */
    public function as_string(): string
    {
        return $this->body();
    }

    /**
     *
     */
    public function dump_json(): void
    {
        print_r($this->json());
    }

    public function getDetails(): array
    {
        return [
            'body'    => $this->_body,
            'headers' => $this->_headers,
            'error'   => $this->error(),
            'info'    => $this->_info,
        ];
    }

    /**
     * @param bool $result
     * @return string
     */
    public function dump(bool $result = false): string
    {
        $msg = "\n--------------------------- Response -------------------------------------\nBODY:\n";
        $msg .= print_r($this->_body, true);
        $msg .= "\nHEAD:\n";
        $msg .= print_r($this->_headers, true);
        $msg .= "\nERROR:\n" . $this->error();
        $msg .= "\nINFO:\n";
        $msg .= json_encode($this->_info);
        $msg .= "\n----------------------------------------------------------------------\n";

        if ($result) {
            return $msg;
        }

        echo $msg;

        return '';
    }

    /**
     * @param int $size
     * @param string $unit
     * @return string
     */
    private function humanFileSize(int $size, string $unit = ''): string
    {
        if ((!$unit && $size >= 1 << 30) || $unit == 'GB') {
            return number_format($size / (1 << 30), 2) . ' GB';
        }
        if ((!$unit && $size >= 1 << 20) || $unit == 'MB') {
            return number_format($size / (1 << 20), 2) . ' MB';
        }
        if ((!$unit && $size >= 1 << 10) || $unit == 'KB') {
            return number_format($size / (1 << 10), 2) . ' KB';
        }

        return number_format($size) . ' bytes';
    }

    /**
     * @return string
     */
    public function upload_content_length(): string
    {
        return $this->humanFileSize($this->_info['upload_content_length']);
    }

    /**
     * @return string
     */
    public function speed_upload(): string
    {
        $SPEED_UPLOAD = $this->_info['speed_upload'];
        return round(($SPEED_UPLOAD * 8) / (1000 * 1000), 2) . ' Mbps';
    }

    /**
     * @return string
     */
    public function speed_download(): string
    {
        $SPEED_UPLOAD = $this->_info['speed_download'];
        return round(($SPEED_UPLOAD * 8) / (1000 * 1000), 2) . ' Mbps';
    }

    /**
     * @return string
     */
    public function size_upload(): string
    {
        return $this->humanFileSize($this->_info['size_upload']);
    }

    /**
     * @return string
     */
    public function request_size(): string
    {
        return $this->humanFileSize($this->_info['request_size']);
    }

    /**
     * @return string
     */
    public function header_size(): string
    {
        return $this->humanFileSize($this->_info['header_size']);
    }

    /**
     * @return string
     */
    public function size_download(): string
    {
        return $this->humanFileSize($this->_info['size_download']);
    }

    /**
     * @return array
     */
    public function info(): array
    {
        return $this->_info;
    }
    /**
     * @param string|null $key
     * @return mixed
     */
    public function json(?string $key = null): mixed
    {
        $d = json_decode($this->body(), true);

        if (!$key) {
            return $d;
        }

        if (!isset($d[$key])) {
            return false;
        }

        return $d[$key];
    }

    /**
     * @return mixed
     */
    public function rawDataOrJson(mixed $format): mixed
    {
        // JSONCompact // JSONEachRow

        if (stripos($format, 'json') !== false)
        {
            if (stripos($format,'JSONEachRow')===false)
            return $this->json();
        }
        return $this->body();

    }
}
