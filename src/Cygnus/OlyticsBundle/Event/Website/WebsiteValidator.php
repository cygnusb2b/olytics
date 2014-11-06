<?php

namespace Cygnus\OlyticsBundle\Event\Website;

use Cygnus\OlyticsBundle\Event\Validator;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;
use Cygnus\OlyticsBundle\Model\Event\WebsiteEvent;
use Cygnus\OlyticsBundle\Model\Session\WebsiteSession;
use Cygnus\OlyticsBundle\Exception\Model\InvalidEventException;
use Cygnus\OlyticsBundle\Exception\Model\InvalidSessionException;

class WebsiteValidator extends Validator
{
    public function validate(EventInterface $event)
    {
        if (!$event instanceof WebsiteEvent) {
            throw InvalidEventException::wrongInstance($event, 'Cygnus\OlyticsBundle\Model\Event\WebsiteEvent');
        }

        parent::validate($event);

        if (!$event->getSession() instanceof WebsiteSession) {
            throw InvalidEventException::invalidSession($event, $event->getSession(), 'Cygnus\OlyticsBundle\Model\Session\WebsiteSession');
        }

        return $this->validateSession($event);
    }

    protected function validateSession(EventInterface $event)
    {
        $session = $event->getSession();
        $id = $session->getId();
        $visitorId = $session->getVisitorId();

        if (empty($id)) {
            throw InvalidSessionException::missingId($event, $id);
        }
        if (empty($visitorId)) {
            throw InvalidSessionException::missingVisitorId($event, $visitorId);
        }
        return true;
    }
}
