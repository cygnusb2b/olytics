<?php
namespace Cygnus\OlyticsBundle\Model\Event;

use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Model\Session\WebsiteSession;

class WebsiteEvent extends AbstractEvent
{
    /**
     * The event container
     *
     * @var Cygnus\OlyticsBundle\Model\Metadata\Entity
     */
    protected $container;

    /**
     * The event session
     *
     * @var Cygnus\OlyticsBundle\Model\Session\WebsiteSession
     */
    protected $session;

    public function isValid()
    {
        $parentValid = AbstractEvent::isValid();

        if ($parentValid && !empty($this->container) && !empty($this->session)) {
            return ($this->container->isValid() && $this->session->isValid());
        } else {
            return false;
        }
    }

    /**
     * Set container
     *
     * @param Cygnus\OlyticsBundle\Model\Metadata\Entity $container
     * @return self
     */
    public function setContainer(Entity $container)
    {
        $this->container = $container;
        return $this;
    }

    /**
     * Get container
     *
     * @return Cygnus\OlyticsBundle\Model\Metadata\Entity $container
     */
    public function getContainer()
    {
        return $this->container;
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
