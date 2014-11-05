<?php
namespace Cygnus\OlyticsBundle\Model\Event;

use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Model\Session\WebsiteSession;
use Cygnus\OlyticsBundle\Model\Exception\InvalidEventException;

class WebsiteEvent extends AbstractEvent
{
    /**
     * The event session
     *
     * @var Cygnus\OlyticsBundle\Model\Session\WebsiteSession
     */
    protected $session;

    public function isValid()
    {
        AbstractEvent::isValid();

        if (!$this->session instanceof WebsiteSession) {
            throw InvalidEventException::invalidSession($this->session, 'Cygnus\OlyticsBundle\Model\Session\WebsiteSession');
        }

        return $this->session->isValid();
    }

    /**
     * Set session
     *
     * @param Cygnus\OlyticsBundle\Model\Session\WebsiteSession $session
     * @return self
     */
    public function setSession(WebsiteSession $session)
    {
        $this->session = $session;
        return $this;
    }

    /**
     * Get session
     *
     * @return Cygnus\OlyticsBundle\Model\Session\WebsiteSession $session
     */
    public function getSession()
    {
        return $this->session;
    }
}
