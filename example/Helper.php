<?php

namespace ClickHouseDB\Example;

class Helper
{
    public static function init()
    {
        date_default_timezone_set('Europe/Moscow');
        error_reporting( E_ALL );
        ini_set('display_errors',1);
    }

        /**
     * @param $file_name
     * @param int $from_id
     * @param int $to_id
     */
    public static function makeListSitesKeysDataFile($file_name, $from_id = 1000, $to_id = 20000)
    {
        @unlink($file_name);

        $handle = fopen($file_name, 'w');
        $rows = 0;

        for ($f = $from_id; $f < $to_id; $f++) {
            $j['site_id'] = $f;
            $j['site_hash'] = md5($f);

            fputcsv($handle, $j);
            $rows = $rows + 1;
        }

        fclose($handle);

        echo "Created file  [$file_name]: $rows rows...\n";
    }


    /**
     * @param $size
     * @param string $unit
     * @return string
     */
    public static function humanFileSize($size, $unit = '')
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
     * @param $file_name
     * @param int $size
     */
    public static function makeSomeDataFile($file_name, $size = 10)
    {
        @unlink($file_name);

        $handle = fopen($file_name, 'w');
        $z = 0;
        $rows = 0;
        $j = [];

        for ($ules = 0; $ules < $size; $ules++) {
            for ($dates = 0; $dates < 5; $dates++) {
                for ($site_id = 12; $site_id < 49; $site_id++) {
                    for ($hours = 0; $hours < 24; $hours++) {
                        $z++;

                        $dt = strtotime('-' . $dates . ' day');
                        $dt = strtotime('-' . $hours . ' hour', $dt);

                        $j = [];
                        $j['event_time'] = date('Y-m-d H:00:00', $dt);
                        $j['url_hash'] = 'XXXX' . $site_id . '_' . $ules;
                        $j['site_id'] = $site_id;
                        $j['views'] = 1;

                        foreach (['00', 55] as $key) {
                            $z++;
                            $j['v_' . $key] = ($z % 2 ? 1 : 0);
                        }

                        fputcsv($handle, $j);
                        $rows++;
                    }
                }
            }
        }

        fclose($handle);

        echo "Created file  [$file_name]: $rows rows...\n";
    }


    /**
     * @param $file_name
     * @param int $size
     * @return bool
     */
    public static function makeSomeDataFileBigOldDates($file_name, $size = 10)
    {
        if (is_file($file_name)) {
            echo "Exist file  [$file_name]: ± rows... size = " . self::humanFileSize(filesize($file_name)) . " \n";
            return false;
        }

        @unlink($file_name);


        $handle = fopen($file_name, 'w');
        $rows = 0;

        for ($day_ago = 0; $day_ago < 360; $day_ago++) {
            $date = strtotime('-' . $day_ago . ' day');
            for ($hash_id = 1; $hash_id < (1 + $size); $hash_id++)
                for ($site_id = 100; $site_id < 199; $site_id++) {
                    $j['event_time'] = date('Y-m-d H:00:00', $date);
                    $j['site_id'] = $site_id;
                    $j['hash_id'] = $hash_id;
                    $j['views'] = 1;

                    fputcsv($handle, $j);
                    $rows++;
                }
        }

        fclose($handle);

        echo "Created file  [$file_name]: $rows rows... size = " . self::humanFileSize(filesize($file_name)) . " \n";
    }


    /**
     * @param $file_name
     * @param int $size
     * @return bool
     */
    public static function makeSomeDataFileBig($file_name, $size = 10, $shift = 0)
    {
        if (is_file($file_name)) {
            echo "Exist file  [$file_name]: ± rows... size = " . self::humanFileSize(filesize($file_name)) . " \n";
            return false;
        }

        @unlink($file_name);


        $handle = fopen($file_name, 'w');
        $z = 0;
        $rows = 0;
        $j = [];

        for ($ules = 0; $ules < $size; $ules++) {
            for ($dates = 0; $dates < 5; $dates++) {
                for ($site_id = 12; $site_id < 49; $site_id++) {
                    for ($hours = 0; $hours < 24; $hours++) {
                        $z++;

                        $dt = strtotime('-' . ($dates + $shift) . ' day');
                        $dt = strtotime('-' . $hours . ' hour', $dt);

                        $j = [];
                        $j['event_time'] = date('Y-m-d H:00:00', $dt);
                        $j['url_hash'] = sha1('XXXX' . $site_id . '_' . $ules) . sha1(microtime() . $site_id . ' ' . mt_rand()) . sha1('XXXX' . $site_id . '_' . $ules);
                        $j['site_id'] = $site_id;
                        $j['views'] = 1;

                        foreach (['00', 55] as $key) {
                            $z++;
                            $j['v_' . $key] = ($z % 2 ? 1 : 0);
                        }

                        fputcsv($handle, $j);
                        $rows++;
                    }
                }
            }
        }

        fclose($handle);

        echo "Created file  [$file_name]: $rows rows... size = " . self::humanFileSize(filesize($file_name)) . " \n";
    }

}