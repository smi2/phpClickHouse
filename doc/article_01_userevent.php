<?php

/**
 * Class UserEvent
 */
class UserEvent
{
    /**
     * @return string
     */
    public function getDate()
    {
        return date('Y-m-d');
    }

    /**
     * @return int
     */
    public function getTime()
    {
        return time();
    }

    /**
     * @return string
     */
    public function getType()
    {
        return (mt_rand(0, 10) > 6 ? 'CLICKS' : 'VIEWS');
    }

    /**
     * @return int
     */
    public function getSiteId()
    {
        return mt_rand(1, 3);
    }

    /**
     * @return int
     */
    public function getArticleId()
    {
        return mt_rand(1000, 2000);
    }

    /**
     * @return mixed
     */
    public function getIp()
    {
        return long2ip(mt_rand(1, 200));
    }

    /**
     * @return string
     */
    public function getCity()
    {
        $cities = [
            'Moscow',
            'Rwanda',
            'Banaadir',
            'Tobruk',
            'Gisborne',
        ];

        return $cities[array_rand($cities)];
    }

    /**
     * @return string
     */
    public function getUserUuid()
    {
        return sha1(mt_rand(1, 100));
    }

    /**
     * @return string
     */
    public function getReferer()
    {
        $referers = [
            'http://yandex.ru/?utm_campaign=',
            'http://smi2.ru/?utm_campaign=',
        ];

        return mt_rand(0, 10) > 6
            ? $referers[array_rand($referers)] . $this->getUtm()
            : '';
    }

    /**
     * @return string
     */
    public function getUtm()
    {
        return mb_substr(md5(mt_rand(0, 100)), 0, 5);
    }
}