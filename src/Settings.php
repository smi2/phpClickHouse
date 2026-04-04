<?php

namespace ClickHouseDB;

class Settings
{
    private array $settings = [];

    private bool $_ReadOnlyUser = false;

    public function __construct()
    {
        $default = [
            'extremes' => false,
            'readonly' => true,
            'max_execution_time' => 20.0,
            'enable_http_compression' => 1,
            'https' => false,
        ];

        $this->settings = $default;
    }

    public function get(string|int $key): mixed
    {
        if (!$this->is($key)) {
            return null;
        }
        return $this->settings[$key];
    }

    public function is(string|int $key): bool
    {
        return isset($this->settings[$key]);
    }


    public function set(string|int $key, mixed $value): static
    {
        $this->settings[$key] = $value;
        return $this;
    }

    public function getDatabase(): mixed
    {
        return $this->get('database');
    }

    public function database(string $db): static
    {
        $this->set('database', $db);
        return $this;
    }

    public function getTimeOut(): int
    {
        return (int) $this->get('max_execution_time');
    }

    public function isEnableHttpCompression(): mixed
    {
        return $this->getSetting('enable_http_compression');
    }

    public function enableHttpCompression(bool|int $flag): static
    {
        $this->set('enable_http_compression', intval($flag));
        return $this;
    }


    public function https(bool $flag = true): static
    {
        $this->set('https', $flag);
        return $this;
    }

    public function isHttps(): mixed
    {
        return $this->get('https');
    }


    public function readonly(int|bool $flag): static
    {
        $this->set('readonly', $flag);
        return $this;
    }

    public function session_id(string $session_id): static
    {
        $this->set('session_id', $session_id);
        return $this;
    }

    public function getSessionId(): string|false
    {
        if (empty($this->settings['session_id'])) {
            return false;
        }
        return $this->get('session_id');
    }

    public function makeSessionId(): string|false
    {
        $this->session_id(sha1(uniqid('', true)));
        return $this->getSessionId();
    }

    /**
     *
     * max_execution_time - is integer in Seconds clickhouse source
     *
     */
    public function max_execution_time(int $time): static
    {
        $this->set('max_execution_time',$time);
        return $this;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function apply(array $settings_array): static
    {
        foreach ($settings_array as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    public function setReadOnlyUser(bool $flag): void
    {
        $this->_ReadOnlyUser = $flag;
    }

    public function isReadOnlyUser():bool
    {
        return $this->_ReadOnlyUser;
    }

    public function getSetting(string $name): mixed
    {
        if (!isset($this->settings[$name])) {
            return null;
        }

        return $this->get($name);
    }

    public function clear():void
    {
        $this->settings = [];
    }
}
