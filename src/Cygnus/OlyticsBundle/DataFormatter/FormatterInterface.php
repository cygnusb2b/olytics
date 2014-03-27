<?php
namespace Cygnus\OlyticsBundle\DataFormatter;

use Symfony\Component\HttpFoundation\ParameterBag;

interface FormatterInterface
{
    public function format(ParameterBag $data);
    public function formatFromArray(array $data);
    public function formatRaw(array $data, array $formattedData = array());
}