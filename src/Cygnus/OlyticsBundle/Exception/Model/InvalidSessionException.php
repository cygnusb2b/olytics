<?php

namespace Cygnus\OlyticsBundle\Exception\Model;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class InvalidSessionException extends InvalidModelException
{
    public static function missingId(EventInterface $event, $id)
    {
        return new self($event, sprintf('The session id cannot be empty. The id provided was %s', serialize($id)), 30101);
    }

    public static function missingVisitorId(EventInterface $event, $id)
    {
        return new self($event, sprintf('The session visitor id cannot be empty. The id provided was %s', serialize($id)), 30102);
    }
}
