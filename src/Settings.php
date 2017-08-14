<?php

namespace ClickHouseDB;

use ClickHouseDB\Transport\Http;

/**
 * Class Settings
 * @package ClickHouseDB
 */
class Settings
{
    /**
     * @var Client
     */
    private $client = false;

    /**
     * @var array
     */
    private $settings = [];

    private $_ReadOnlyUser=false;

    /**
     * @var bool
     */
    private $_isHttps=false;

    /**
     * Settings constructor.
     * @param Http $client
     */
    public function __construct(Http $client)
    {
        $default = [
            'extremes'                => false,
            'readonly'                => true,
            'max_execution_time'      => 20,
            'enable_http_compression' => 0,
            'https'                   => false
        ];

        $this->settings = $default;
        $this->client = $client;
    }

    /**
     * @param $key
     * @return mixed
     */
    private function get($key)
    {
        return $this->settings[$key];
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function set($key, $value)
    {
        $this->settings[$key] = $value;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDatabase()
    {
        return $this->get('database');
    }

    /**
     * @param $db
     * @return $this
     */
    public function database($db)
    {
        $this->set('database', $db);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTimeOut()
    {
        return $this->get('max_execution_time');
    }

    /**
     * @return mixed|null
     */
    public function isEnableHttpCompression()
    {
        return $this->getSetting('enable_http_compression');
    }

    /**
     * @param $flag
     * @return $this
     */
    public function enableHttpCompression($flag)
    {
        $this->set('enable_http_compression', intval($flag));
        return $this;
    }


    public function https($flag=true)
    {
        $this->set('https', $flag);
        return $this;
    }

    public function isHttps()
    {
        return $this->get('https');
    }


    /**
     * @param $flag
     * @return $this
     */
    public function readonly($flag)
    {
        $this->set('readonly', $flag);
        return $this;
    }

    /**
     * @param $session_id
     * @return $this
     */
    public function session_id($session_id)
    {
        $this->set('session_id', $session_id);
        return $this;
    }
    /**
     * @return string
     */
    public function getSessionId()
    {
        if (empty($this->settings['session_id'])) return false;
        return $this->get('session_id');
    }

    /**
     * @return string
     */
    public function makeSessionId()
    {
        $this->session_id(sha1(uniqid('', true)));
        return $this->getSessionId();
    }

    /**
     * @param $time
     * @return $this
     */
    public function max_execution_time($time)
    {
        $this->set('max_execution_time', $time);
        return $this;
    }

    /**
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * @param $settings_array
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
     * @param $flag
     */
    public function setReadOnlyUser($flag)
    {
        $this->_ReadOnlyUser=$flag;
    }

    /**
     *
     *
     */
    public function isReadOnlyUser()
    {
        return $this->_ReadOnlyUser;
    }

    /**
     * @param $name
     * @return mixed|null
     */
    public function getSetting($name)
    {
        if (!isset($this->settings[$name])) {
            return null;
        }

        return $this->get($name);
    }
}