<?php

namespace Cygnus\OlyticsBundle\Exception\Model;

use Cygnus\OlyticsBundle\Exception\ExceptionInterface;
use Cygnus\OlyticsBundle\Exception\JsonMessagedInterface;
use Cygnus\OlyticsBundle\Exception\JsonMessagedTrait;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;
use \Exception;

class InvalidModelException extends Exception implements ExceptionInterface, JsonMessagedInterface
{
    use JsonMessagedTrait;

    protected $event;

    public function __construct(EventInterface $event, $message = '', $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->event = $event;
        $this->setResponseCode(400);
        $this->setResponseBody(['created' => false, 'reason' => $this->getReason(), 'code' => $this->getCode()]);
    }

    public function getEvent()
    {
        return $this->event;
    }

    public function getReason()
    {
        $classParts = explode('\\', get_class($this));
        $baseClass = array_pop($classParts);
        return str_replace('Exception', '', $baseClass);
    }
}
