<?php
namespace ClickHouseDB;

/**
 * Class StrictQuote
 * @package ClickHouseDB
 */
class StrictQuoteLine
{

    private $preset=[
        'CSV'=>[
            'EnclosureArray'=>'"',
            'EncodeEnclosure'=>'"',
            'Enclosure'=>'"',
            'Null'=>"",
            'Delimiter'=>",",
            'TabEncode'=>false,
        ],
        'Insert'=>[
            'EnclosureArray'=>'',
            'EncodeEnclosure'=>'\\',
            'Enclosure'=>'\'',
            'Null'=>"",
            'Delimiter'=>",",
            'TabEncode'=>false,
        ],
        'TSV'=>[
            'EnclosureArray'=>'',
            'EncodeEnclosure'=>'',
            'Enclosure'=>'\\',
            'Null'=>" ",
            'Delimiter'=>"\t",
            'TabEncode'=>true,
        ],
    ];
    private $settings=[];

    public function __construct($format)
    {
        if (empty($this->preset[$format]))
        {
            throw new QueryException("Unsupport format encode line:".$format);
        }
        $this->settings=$this->preset[$format];
    }
    public function quoteRow($row)
    {
        return implode($this->settings['Delimiter'],$this->quoteValue($row));
    }
    public function quoteValue($row)
    {
        $enclosure = $this->settings['Enclosure'];
        $delimiter = $this->settings['Delimiter'];
        $encode = $this->settings['EncodeEnclosure'];
        $encodeArray = $this->settings['EnclosureArray'];
        $null = $this->settings['Null'];
        $tabEncode=$this->settings['TabEncode'];


        $quote = function ($value) use ($enclosure,$delimiter,$encode,$encodeArray,$null,$tabEncode) {



            $delimiter_esc = preg_quote($delimiter, '/');

            $enclosure_esc = preg_quote($enclosure, '/');

            $encode_esc = preg_quote($encode, '/');

            $type = gettype($value);

            if ($type == 'integer' || $type == 'double') {
                return strval($value);
            }

            if (is_string($value)) {
                if ($tabEncode)
                {
                    return str_replace(["\t","\n"],['\\t','\\n'],$value);
                }

                $value = strval($value);
                $value = preg_replace('/('.$enclosure_esc.'|'.$encode_esc.')/',$encode_esc.'\1', $value);
                return $enclosure . $value . $enclosure;
            }

            if (is_array($value)) {
                // Массивы форматируются в виде списка значений через запятую в квадратных скобках.
                // Элементы массива - числа форматируются как обычно, а даты, даты-с-временем и строки - в одинарных кавычках с такими же правилами экранирования, как указано выше.
                // Массивы сериализуются в InsertRow следующим образом: сначла массив сериализуется в строку,
                // как в формате TabSeparated, а затем полученная строка выводится в InsertRow в двойных кавычках.


                $result_array = FormatLine::Insert($value);

                return $encodeArray . '[' . $result_array . ']' .$encodeArray;
            }

            if (null === $value) {
                return $null;
            }

            return $value;
        };

        return array_map($quote, $row);
    }


}