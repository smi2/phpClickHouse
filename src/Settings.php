<?php

namespace ClickHouseDB;

/**
 * @see https://clickhouse.yandex/docs/en/operations/settings/
 */
class Settings
{
    /** @var mixed[] */
    private $settings = [];

    /** @var bool */
    private $httpCompressionEnabled = false;

    /**
     * @return mixed|null
     */
    public function get(string $key)
    {
        if (! $this->isSet($key)) {
            return null;
        }

        return $this->settings[$key];
    }

    public function isSet(string $key) : bool
    {
        return isset($this->settings[$key]);
    }

    /**
     * @param mixed $value
     */
    public function set(string $key, $value) : void
    {
        $this->settings[$key] = $value;
    }

    /**
     * @return mixed|null
     */
    public function isHttpCompressionEnabled()
    {
        return $this->httpCompressionEnabled;
    }

    public function setHttpCompression(bool $enable) : self
    {
        $this->httpCompressionEnabled = $enable;

        return $this;
    }

    public function readonly(int $flag) : self
    {
        $this->set('readonly', $flag);

        return $this;
    }

    /**
     * @param string $session_id
     * @return $this
     */
    public function session_id($session_id)
    {
        $this->set('session_id', $session_id);

        return $this;
    }

    /**
     * @return mixed|bool
     */
    public function getSessionId()
    {
        if (empty($this->settings['session_id'])) {
            return false;
        }

        return $this->get('session_id');
    }

    /**
     * @return string|bool
     */
    public function makeSessionId()
    {
        $this->session_id(sha1(uniqid('', true)));

        return $this->getSessionId();
    }

    /**
     * @param mixed[] $forcedSettings
     *
     * @return mixed[]
     */
    public function getQueryableSettings(array $forcedSettings) : array
    {
        $settings = $this->settings;
        if (! empty($forcedSettings)) {
            $settings = $forcedSettings + $this->settings;
        }

        return $settings;
    }

    /**
     * @param array $settings_array
     * @return $this
     */
    public function apply($settings_array)
    {
        foreach ($settings_array as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function getSetting($name)
    {
        if (! isset($this->settings[$name])) {
            return null;
        }

        return $this->get($name);
    }
}
