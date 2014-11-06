<?php
namespace Cygnus\OlyticsBundle\Event;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;

interface ValidatorInterface
{
    public function validate(EventInterface $event);
}