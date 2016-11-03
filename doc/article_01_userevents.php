<?php
class UserEvents
{
    public function trim($value)
    {
        // грязно удаляем
        return str_replace(["\n","\t","\r",'"'],'',$value);
    }
    public function getDate()
    {
        return date('Y-m-d');
    }

    public function getIp()
    {
        return $this->trim($_SERVER['REMOTE_ADDR']);
    }
    public function getArticleId()
    {
        return mt_rand(1000,2000);
    }
    public function getSiteId()
    {
        return mt_rand(1,3);
    }


    public function getType()
    {
        return (mt_rand(0,1)>0.6?'CLICKS':'VIEWS');
    }

    public function getTime()
    {
        return time();
    }
}