<?php

namespace Cygnus\OlyticsBundle\Model\Exception;

class InvalidEntityException extends InvalidModelException
{

    public static function missingType($type)
    {
        return new self(sprintf('The entity type cannot be empty. The type provided was %s', serialize($type)));
    }

    public static function missingClientId($clientId)
    {
        return new self(sprintf('The entity client ID cannot be empty. The id provided was %s', serialize($clientId)));
    }
}
