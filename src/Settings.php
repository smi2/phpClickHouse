<?php
namespace ClickHouseDB;

class Settings
{
    /**
     * @var Client
     */
    private $client=false;
    private $settings=[];
//    const ALWAYS_SETS=[
//        'extremes','readonly','max_rows_to_read','max_execution_time','database'
//    ];

    public function __construct(\ClickHouseDB\Transport\Http $client)
    {
        $default=[
            'extremes'=>true,
            'readonly'=>true,
            'max_rows_to_read'=>1000000000,
            'max_execution_time'=>20
//            'database'=>false
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
    private function set($key,$value)
    {
        $this->settings[$key]=$value;
        return $this;
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
}