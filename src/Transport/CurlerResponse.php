<?php

namespace ClickHouseDB\Transport;

class CurlerResponse
{
    public array $_headers = [];

    public array $_info = [];

    public string $_error = '';

    public int $_errorNo = 0;

    public float $_useTime = 0.0;

    public string $_body = '';


    public function __construct() {}


    public function error_no(): int
    {
        return $this->_errorNo;
    }

    public function error(): string
    {
        return $this->_error;
    }

    public function url(): string
    {
        return $this->_info['url'];
    }

    public function total_time(): float
    {
        return round($this->_info['total_time'], 3);
    }

    public function starttransfer_time(): float
    {
        return round($this->_info['starttransfer_time'], 3);
    }

    public function connect_time(): float
    {
        return round($this->_info['connect_time'], 3);
    }

    public function pretransfer_time(): float
    {
        return round($this->_info['pretransfer_time'], 3);
    }

    public function content_type(): ?string
    {
        return $this->_info['content_type'];
    }

    public function http_code(): int
    {
        return $this->_info['http_code'];
    }

    public function headers(string $name): ?string
    {
        if (isset($this->_headers[$name])) {
            return $this->_headers[$name];
        }

        return null;
    }

    public function connection(): ?string
    {
        return $this->headers('Connection');
    }

    public function body(): string
    {
        return $this->_body;
    }

    public function as_string(): string
    {
        return $this->body();
    }

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

    public function upload_content_length(): string
    {
        return $this->humanFileSize($this->_info['upload_content_length']);
    }

    public function speed_upload(): string
    {
        $SPEED_UPLOAD = $this->_info['speed_upload'];
        return round(($SPEED_UPLOAD * 8) / (1000 * 1000), 2) . ' Mbps';
    }

    public function speed_download(): string
    {
        $SPEED_UPLOAD = $this->_info['speed_download'];
        return round(($SPEED_UPLOAD * 8) / (1000 * 1000), 2) . ' Mbps';
    }

    public function size_upload(): string
    {
        return $this->humanFileSize($this->_info['size_upload']);
    }

    public function request_size(): string
    {
        return $this->humanFileSize($this->_info['request_size']);
    }

    public function header_size(): string
    {
        return $this->humanFileSize($this->_info['header_size']);
    }

    public function size_download(): string
    {
        return $this->humanFileSize($this->_info['size_download']);
    }

    public function info(): array
    {
        return $this->_info;
    }

    public function json(mixed $key = null): mixed
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
