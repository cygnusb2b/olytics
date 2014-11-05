<?php

namespace Cygnus\OlyticsBundle\Model\Exception;

class InvalidSessionException extends InvalidModelException
{

    public static function missingId($id)
    {
        return new self(sprintf('The session id cannot be empty. The id provided was %s', serialize($id)));
    }

    public static function missingVisitorId($id)
    {
        return new self(sprintf('The session visitor id cannot be empty. The id provided was %s', serialize($id)));
    }
}
