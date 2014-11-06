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
