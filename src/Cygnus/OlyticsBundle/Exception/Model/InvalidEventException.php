<?php

namespace Cygnus\OlyticsBundle\Exception\Model;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class InvalidEventException extends InvalidModelException
{
    public static function wrongInstance(EventInterface $event, $expected)
    {
        return new self($event, sprintf('The provided event instance is invalid. Got %s but expected %s', get_class($event), $expected), 10101);
    }

    public static function invalidEntity(EventInterface $event, $entity, $expected)
    {
        return new self($event, sprintf('The event entity is not valid. Expected "%s" but received: %s', $expected, serialize($entity)), 10102);
    }

    public static function missingAction(EventInterface $event, $action)
    {
        return new self($event, sprintf('The event action cannot be empty. The action provided was %s', serialize($action)), 10103);
    }

    public static function invalidSession(EventInterface $event, $session, $expected)
    {
        return new self($event, sprintf('The event session is not valid. Expected "%s" but received: %s', $expected, serialize($session)), 10104);
    }
}
