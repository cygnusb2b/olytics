<?php

namespace Cygnus\OlyticsBundle\Exception\Model;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class InvalidEntityException extends InvalidModelException
{
    /**
     * Generates a new Exception
     * The Entity is missing a type
     * Code: 20101
     *
     * @static
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  mixed                                            $type
     * @return self
     */
    public static function missingType(EventInterface $event, $type)
    {
        return new self($event, sprintf('The entity type cannot be empty. The type provided was %s', serialize($type)), 20101);
    }

    /**
     * Generates a new Exception
     * The Entity is missing a client id
     * Code: 20102
     *
     * @static
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  mixed                                            $clientId
     * @return self
     */
    public static function missingClientId(EventInterface $event, $clientId)
    {
        return new self($event, sprintf('The entity client ID cannot be empty. The id provided was %s', serialize($clientId)), 20102);
    }
}
