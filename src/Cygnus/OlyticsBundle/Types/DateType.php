<?php
namespace Cygnus\OlyticsBundle\Types;

use \DateTime;
use \MongoDate;

class DateType extends AbstractType
{
    public static function convert($value) {

        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTime) {
            return $value;
        }

        $date = new DateTime();

        if ($value instanceof MongoDate) {
            $date->setTimestamp($value->sec);
            return $date;
        }

        

        if (is_numeric($value)) {
            $date->setTimestamp($value);
        } elseif (is_string($value)) {
            $date->setTimestamp(strtotime($value));
        }
        return $date;
    }
}
