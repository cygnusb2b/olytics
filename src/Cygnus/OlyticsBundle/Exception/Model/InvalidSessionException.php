<?php

namespace Cygnus\OlyticsBundle\Exception\Model;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class InvalidSessionException extends InvalidModelException
{
    /**
     * Generates a new Exception
     * The Session is missing a valid id
     * Code: 30101
     *
     * @static
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  mixed                                            $id
     * @return self
     */
    public static function missingId(EventInterface $event, $id)
    {
        return new self($event, sprintf('The session id cannot be empty. The id provided was %s', serialize($id)), 30101);
    }

    /**
     * Generates a new Exception
     * The Session is missing a valid visitor id
     * Code: 30102
     *
     * @static
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  mixed                                            $id
     * @return self
     */
    public static function missingVisitorId(EventInterface $event, $id)
    {
        return new self($event, sprintf('The session visitor id cannot be empty. The id provided was %s', serialize($id)), 30102);
    }
}
