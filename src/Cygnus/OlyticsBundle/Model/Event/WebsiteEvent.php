<?php
namespace Cygnus\OlyticsBundle\Model\Event;

use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Model\Session\WebsiteSession;

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
        $parentValid = AbstractEvent::isValid();

        if ($parentValid && !empty($this->session)) {
            return ($this->session->isValid());
        } else {
            return false;
        }
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
