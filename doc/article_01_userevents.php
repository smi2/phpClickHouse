<?php

/**
 * Class UserEvents
 */
class UserEvents
{
    /**
     * @param $value
     * @return mixed
     */
    public function trim($value)
    {
        // грязно удаляем
        return str_replace(["\n", "\t", "\r", '"'], '', $value);
    }

    /**
     * @return false|string
     */
    public function getDate()
    {
        return date('Y-m-d');
    }

    /**
     * @return mixed
     */
    public function getIp()
    {
        return $this->trim($_SERVER['REMOTE_ADDR']);
    }

    /**
     * @return int
     */
    public function getArticleId()
    {
        return mt_rand(1000, 2000);
    }

    /**
     * @return int
     */
    public function getSiteId()
    {
        return mt_rand(1, 3);
    }

    /**
     * @return string
     */
    public function getType()
    {
        return (mt_rand(0, 1) > 0.6 ? 'CLICKS' : 'VIEWS');
    }

    /**
     * @return int
     */
    public function getTime()
    {
        return time();
    }
}