<?php
namespace ClickHouseDB;

class Settings
{
    /**
     * @var Client
     */
    private $client=false;
    private $settings=[];

    public function __construct(\ClickHouseDB\Transport\Http $client)
    {
        $default=[
            'extremes'=>true,
            'readonly'=>true,
            'max_rows_to_read'=>10000000,
            'max_execution_time'=>20,
            'enable_http_compression'=>0
            //'max_insert_block_size'=>1000000000, 'max_block_size'=>10000000,
        ];
        $this->settings=$default;
        $this->client=$client;
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
    public function set($key,$value)
    {
        $this->settings[$key]=$value;
        return $this;
    }
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
        $this->set('database',$db);
        return $this;
    }

    public function getTimeOut()
    {
        return $this->get('max_execution_time');
    }
    public function isEnableHttpCompression()
    {
        return $this->getSetting('enable_http_compression');
    }
    public function enableHttpCompression($flag)
    {
        $this->set('enable_http_compression',intval($flag));
        return $this;
    }
    /**
     * @param $flag
     * @return $this
     */
    public function readonly($flag)
    {
        $this->set('readonly',$flag);
        return $this;
    }
    public function max_execution_time($time)
    {
        $this->set('max_execution_time',$time);
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
        foreach ($settings_array as $key=>$value)
        {
            $this->set($key,$value);
        }
        return $this;
    }
    /**
     * @param $name
     */
    public function getSetting($name)
    {
        if (!isset($this->settings[$name])) return null;
        return $this->get($name);
    }
}