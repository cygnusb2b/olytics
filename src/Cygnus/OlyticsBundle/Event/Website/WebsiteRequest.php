<?php
namespace Cygnus\OlyticsBundle\Event\Website;

use Cygnus\OlyticsBundle\Event\Request;
use Symfony\Component\HttpFoundation\ParameterBag;

class WebsiteRequest extends Request
{
    protected $session;

    protected $container;

    public function __construct(array $session, array $container, array $event, $pid)
    {
        $this->session = new ParameterBag($session);
        $this->container = new ParameterBag($container);
        $this->event = new ParameterBag($event);

        $this->setPID($pid);
    }

    public function setPID($pid)
    {
        if (!is_null($pid)) {
            $parts = explode('_', $pid);
            if (isset($parts[0])) $this->vertical = $parts[0];
            if (isset($parts[1])) $this->product = $parts[1];
        }
    }

    public function getSession()
    {
        return $this->session;
    }

    public function getContainer()
    {
        return $this->container;
    }

}