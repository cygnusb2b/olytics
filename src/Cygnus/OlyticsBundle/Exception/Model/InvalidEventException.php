<?php

namespace Cygnus\OlyticsBundle\Exception\Model;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class InvalidEventException extends InvalidModelException
{
    /**
     * Generates a new Exception
     * The Event is the wrong class instance type
     * Code: 10101
     *
     * @static
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $expected
     * @return self
     */
    public static function wrongInstance(EventInterface $event, $expected)
    {
        return new self($event, sprintf('The provided event instance is invalid. Got %s but expected %s', get_class($event), $expected), 10101);
    }

    /**
     * Generates a new Exception
     * The Event Entity is the wrong type
     * Code: 10102
     *
     * @static
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  mixed                                            $entity
     * @param  string                                           $expected
     * @return self
     */
    public static function invalidEntity(EventInterface $event, $entity, $expected)
    {
        return new self($event, sprintf('The event entity is not valid. Expected "%s" but received: %s', $expected, serialize($entity)), 10102);
    }

    /**
     * Generates a new Exception
     * The Event is missing an action
     * Code: 10103
     *
     * @static
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  mixed                                            $action
     * @return self
     */
    public static function missingAction(EventInterface $event, $action)
    {
        return new self($event, sprintf('The event action cannot be empty. The action provided was %s', serialize($action)), 10103);
    }

    /**
     * Generates a new Exception
     * The Event is missing a valid session
     * Code: 10104
     *
     * @static
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  mixed                                            $session
     * @param  string                                           $expected
     * @return self
     */
    public static function invalidSession(EventInterface $event, $session, $expected)
    {
        return new self($event, sprintf('The event session is not valid. Expected "%s" but received: %s', $expected, serialize($session)), 10104);
    }
}
