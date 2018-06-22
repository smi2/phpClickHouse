<?php
namespace ClickHouseDB\Quote;

use ClickHouseDB\Exception\QueryException;

class StrictQuoteLine
{

    private $preset = [
        'CSV'=>[
            'EnclosureArray'=>'"',
            'EncodeEnclosure'=>'"',
            'Enclosure'=>'"',
            'Null'=>"\\N",
            'Delimiter'=>",",
            'TabEncode'=>false,
        ],
        'Insert'=>[
            'EnclosureArray'=>'',
            'EncodeEnclosure'=>'\\',
            'Enclosure'=>'\'',
            'Null'=>"NULL",
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
    private $settings = [];

    public function __construct($format)
    {
        if (empty($this->preset[$format]))
        {
            throw new QueryException("Unsupport format encode line:" . $format);
        }
        $this->settings = $this->preset[$format];
    }
    public function quoteRow($row)
    {
        return implode($this->settings['Delimiter'], $this->quoteValue($row));
    }
    public function quoteValue($row)
    {
        $enclosure = $this->settings['Enclosure'];
        $delimiter = $this->settings['Delimiter'];
        $encode = $this->settings['EncodeEnclosure'];
        $encodeArray = $this->settings['EnclosureArray'];
        $null = $this->settings['Null'];
        $tabEncode = $this->settings['TabEncode'];


        $quote = function($value) use ($enclosure, $delimiter, $encode, $encodeArray, $null, $tabEncode) {



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
                    return str_replace(["\t", "\n"], ['\\t', '\\n'], $value);
                }

                $value = strval($value);
                $value = preg_replace('/(' . $enclosure_esc . '|' . $encode_esc . ')/', $encode_esc . '\1', $value);
                return $enclosure . $value . $enclosure;
            }

            if (is_array($value)) {
                // Arrays are formatted as a list of values separated by commas in square brackets.
                // Elements of the array - the numbers are formatted as usual, and the dates, dates-with-time, and lines are in
                // single quotation marks with the same screening rules as above.
                // as in the TabSeparated format, and then the resulting string is output in InsertRow in double quotes.
                $result_array = FormatLine::Insert($value);

                return $encodeArray . '[' . $result_array . ']' . $encodeArray;
            }

            if (null === $value) {
                return $null;
            }

            return $value;
        };

        return array_map($quote, $row);
    }


}
