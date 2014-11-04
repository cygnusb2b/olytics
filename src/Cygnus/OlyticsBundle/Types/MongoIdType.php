<?php
namespace Cygnus\OlyticsBundle\Types;

use \MongoId;

class MongoIdType extends AbstractType
{
    public static function convert($value) {
        if ($value === null) {
            return null;
        }
        if ( ! $value instanceof MongoId) {
            try {
                $value = new MongoId($value);
            } catch (\MongoException $e) {
                $value = null;
            }
        }
        return $value;
    }
}
