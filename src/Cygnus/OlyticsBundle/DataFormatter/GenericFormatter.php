<?php
namespace Cygnus\OlyticsBundle\DataFormatter;

use Symfony\Component\HttpFoundation\ParameterBag;

class GenericFormatter extends AbstractFormatter
{

    protected $types = array(
        'date'      => 'Cygnus\OlyticsBundle\Types\DateType',
        'hash'      => 'Cygnus\OlyticsBundle\Types\MD5Type',
        'MongoId'   => 'Cygnus\OlyticsBundle\Types\MongoIdType',
    );

    /**
     * Formats raw (array) data into proper data types. Is recursive.
     * Note: all empty strings will be cast as null.
     *
     * @param  array $data          The array data to format
     * @param  array $formattedData The formatted data (for recursive purposes)
     * @return array The formatted data
     */
    public function formatRaw(array $data, array $formattedData = array())
    {
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                if (is_numeric($v)) {
                    // Numeric string value, convert
                    if (preg_match('/^[+-]?(\d*\.\d+([eE]?[+-]?\d+)?|\d+[eE][+-]?\d+)$/', $v)) {
                        // Convert to float
                        $v = (float) $v;
                    } else {
                        // Convert to integer
                        $v = (int) $v;
                    }
                } elseif (1 === preg_match('/^[0-9a-f]{24}$/i', $v)) {
                    // Handle MongoId formats
                    $v = new \MongoId($v);
                } else {
                    // Non-numeric string, convert bools, nulls, and empty strings
                    $value = strtolower($v);
                    switch ($value) {
                        case 'null':
                            $v = null;
                            break;
                        case 'true':
                            $v = true;
                            break;
                        case 'false':
                            $v = false;
                            break;
                        case '':
                            $v = null;
                            break;
                        default:
                            $v = $this->convertTypes($v);
                            break;
                    }
                }
            } elseif (is_array($v)) {
                // Recursively format sub-objects
                $formattedData[$k] = array();
                $v = $this->formatRaw($v, $formattedData[$k]);
            }
            $formattedData[$k] = $v;
        }
        return $formattedData;
    }

    public function convertTypes($value)
    {
        foreach ($this->types as $type => $typeClass) {
            $formatterName = '$' . $type . '::';
            if (stristr($value, $formatterName) !== false) {
                $value = str_replace($formatterName, '', $value);
                $value = $typeClass::convert($value);
            }
        }
        return $value;
    }
}
