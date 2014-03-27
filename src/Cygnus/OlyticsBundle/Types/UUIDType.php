<?php
namespace Cygnus\OlyticsBundle\Types;

class UUIDType extends AbstractType
{
    public static function convert($value) {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return pack('H*', str_replace('-', '', $value));
        }
        throw new \InvalidArgumentException(sprintf('Could not convert %s to a UUID value', is_scalar($value) ? '"'.$value.'"' : gettype($value)));
    }
}