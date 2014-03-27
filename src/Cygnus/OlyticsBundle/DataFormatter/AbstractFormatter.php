<?php
namespace Cygnus\OlyticsBundle\DataFormatter;

use Symfony\Component\HttpFoundation\ParameterBag;

abstract class AbstractFormatter implements FormatterInterface
{
    /**
     * Formats a ParameterBag object and returns it
     *
     * @param  Symfony\Component\HttpFoundation\ParameterBag $data The data to format
     * @return Symfony\Component\HttpFoundation\ParameterBag The formatted data
     */
    public function format(ParameterBag $data)
    { 
        $formatted = $this->formatRaw($data->all());
        $data->replace($formatted);
        return $data;
    }

    /**
     * Formats from an array and returns it
     * Is a proxy for @see format()
     *
     * @param  array $data The data to format
     * @return Symfony\Component\HttpFoundation\ParameterBag The formatted data
     */
    public function formatFromArray(array $data)
    { 
        return $this->format(new ParameterBag($data));
    }

    /**
     * Formats raw (array) data into proper data types. Is recursive.
     *
     * @param  array $data          The array data to format
     * @param  array $formattedData The formatted data (for recursive purposes)
     * @return array The formatted data
     */
    abstract public function formatRaw(array $data, array $formattedData = array());
}
