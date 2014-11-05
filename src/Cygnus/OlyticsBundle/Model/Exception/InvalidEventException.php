<?php

namespace Cygnus\OlyticsBundle\Model\Exception;

class InvalidEventException extends InvalidModelException
{
    public static function invalidEntity($entity, $expected)
    {
        return new self(sprintf('The event entity is not valid. Expected "%s" but received: %s', $expected, serialize($entity)));
    }

    public static function missingAction($action)
    {
        return new self(sprintf('The event action cannot be empty. The action provided was %s', serialize($action)));
    }

    public static function invalidSession($session, $expected)
    {
        return new self(sprintf('The event session is not valid. Expected "%s" but received: %s', $expected, serialize($session)));
    }
}
