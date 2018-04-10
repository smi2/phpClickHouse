<?php

namespace ClickHouseDB;

/*
// $structure - структура таблицы, в форме UserID UInt64, URL String. Определяет имена и типы столбцов.
// $format - формат данных в файле. Если не указано - используется TabSeparated.
// Имя таблицы берётся из имени файла.
// {name}_format, {name}_types, {name}_structure, где name - имя таблицы,которой соответствуют эти параметры.

Не перечисляйте слишком большое количество значений (миллионы) явно.
Если множество большое - лучше загрузить его во временную таблицу (например, смотрите раздел "Внешние данные для обработки запроса"), и затем воспользоваться подзапросом.

Внешние данные для обработки запроса

При использовании HTTP интерфейса, внешние данные передаются в формате multipart/form-data. Каждая таблица передаётся отдельным файлом. Имя таблицы берётся из имени файла. В query_string передаются параметры name_format, name_types, name_structure, где name - имя таблицы, которой соответствуют эти параметры. Смысл параметров такой же, как при использовании клиента командной строки.

Пример:

cat /etc/passwd | sed 's/:/\t/g' > passwd.tsv

curl -F 'passwd=@passwd.tsv;' 'http://localhost:8123/

?query=SELECT+shell,+count()+AS+c+FROM+passwd+GROUP+BY+shell+ORDER+BY+c+DESC
&passwd_structure=login+String,+unused+String,+uid+UInt16,+gid+UInt16,+comment+String,+home+String,+shell+String'
*/

class WhereInFile
{
    /**
     *
     */
    const FORMAT_TabSeparated          = 'TabSeparated';
    const FORMAT_TabSeparatedWithNames = 'TabSeparatedWithNames';
    const FORMAT_CSV                   = 'CSV';

    /**
     * @var array
     */
    private $_files = [];


    /**
     * WhereInFile constructor.
     */
    public function __construct() {}


    /**
     * @param $file_name
     * @param $table_name
     * @param $structure
     * @param string $format
     */
    public function attachFile($file_name, $table_name, $structure, $format = 'CSV')
    {
        if (!is_readable($file_name)) {
            throw new QueryException('Can`t read file: ' . $file_name);
        }

        $this->_files[$table_name] = [
            'filename'  => $file_name,
            'structure' => $structure,
            'format'    => $format
        ];
    }

    /**
     * @return int
     */
    public function size()
    {
        return sizeof($this->_files);
    }

    /**
     * @return array
     */
    public function fetchFiles()
    {
        $out = [];
        foreach ($this->_files as $table => $data) {
            $out[$table] = realpath($data['filename']);
        }

        return $out;
    }

    /**
     * @param $table
     * @return string
     */
    public function fetchStructure($table)
    {
        $structure = $this->_files[$table]['structure'];

        $out = [];
        foreach ($structure as $name => $type) {
            $out[] = $name . ' ' . $type;
        }

        return implode(',', $out);
    }

    /**
     * @return array
     */
    public function fetchUrlParams()
    {
        $out = [];
        foreach ($this->_files as $table => $data) {
            $out[$table . '_structure'] = $this->fetchStructure($table);
            $out[$table . '_format'] = $data['format'];
        }

        return $out;
    }

}
