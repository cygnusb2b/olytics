<?php
namespace Cygnus\OlyticsBundle\Event;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;

interface ValidatorInterface
{
    /**
     * Validates an EventInterface instance to ensure it is acceptable for database persistence
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @return bool
     * @throws Cygnus\OlyticsBundle\Exception\Model\InvalidModelException
     */
    public function validate(EventInterface $event);
}