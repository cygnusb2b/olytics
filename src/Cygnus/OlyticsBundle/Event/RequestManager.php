<?php
namespace Cygnus\OlyticsBundle\Event;

abstract class RequestManager
{
    abstract public function manage(RequestInterface $eventRequest);
}
