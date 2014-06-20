<?php
namespace Cygnus\OlyticsBundle\Types;

use \DateTime;

class MD5Type extends AbstractType
{
    public static function convert($value) {

        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            $value = serialize($value);
        }

        return md5($value);
    }
}