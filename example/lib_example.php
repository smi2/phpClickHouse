<?php

function makeListSitesKeysDataFile($file_name,$from_id=1000,$to_id=20000)
{
    @unlink($file_name);


    $handle = fopen($file_name,'w');
    $rows=0;
    for($f=$from_id;$f<$to_id;$f++)
    {
        $j['site_id']=$f;
        $j['site_hash']=md5($f);

        fputcsv($handle,$j);
        $rows=$rows+1;
    }

    fclose($handle);

    echo "Created file  [$file_name]: $rows rows...\n";

}
function makeSomeDataFile($file_name,$size=10)
{


    @unlink($file_name);


    $handle = fopen($file_name,'w');
    $z=0;$rows=0;
    $j=[];
    for($ules=0;$ules<$size;$ules++)
        for($dates=0;$dates<5;$dates++)
        {
            for ($site_id=12;$site_id<49;$site_id++)
            {
                for ($hours=0;$hours<24;$hours++)
                {
                    $z++;
                    $dt=strtotime('-'.$dates.' day');
                    $dt=strtotime('-'.$hours.' hour',$dt);
                    $j=[];
                    $j['event_time']=date('Y-m-d H:00:00',$dt);
                    $j['url_hash']='XXXX'.$site_id.'_'.$ules;
                    $j['site_id']=$site_id;
                    $j['views']=1;

                    foreach (['00',55] as $key)
                    {
                        $z++;
                        $j['v_'.$key]=($z%2?1:0);
                    }
                    fputcsv($handle,$j);
                    $rows++;
                }
            }
        }

    fclose($handle);

    echo "Created file  [$file_name]: $rows rows...\n";
}
