<?php

namespace Cygnus\OlyticsBundle\Exception\Model;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class InvalidEntityException extends InvalidModelException
{
    public static function missingType(EventInterface $event, $type)
    {
        return new self($event, sprintf('The entity type cannot be empty. The type provided was %s', serialize($type)), 20101);
    }

    public static function missingClientId(EventInterface $event, $clientId)
    {
        return new self($event, sprintf('The entity client ID cannot be empty. The id provided was %s', serialize($clientId)), 20102);
    }
}
