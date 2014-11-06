<?php

namespace Cygnus\OlyticsBundle\Exception\Model;

use Cygnus\OlyticsBundle\Exception\ExceptionInterface;
use Cygnus\OlyticsBundle\Exception\JsonMessagedInterface;
use Cygnus\OlyticsBundle\Exception\JsonMessagedTrait;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;
use \Exception;

class InvalidModelException extends Exception implements ExceptionInterface, JsonMessagedInterface
{
    /**
     * The JSON Exception message Trait
     * Used for returning JSON response related information
     *
     */
    use JsonMessagedTrait;

    /**
     * The EventInterface instance
     *
     * @var Cygnus\OlyticsBundle\Model\Event\EventInterface
     */
    protected $event;

    /**
     * Constructor.
     * Sets the default Exception arguments
     * Sets the EventInterface instance and the default JSON response code and body
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $message
     * @param  int                                              $code
     * @param  \Exception                                       $previous
     * @return void
     */
    public function __construct(EventInterface $event, $message = '', $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->event = $event;
        $this->setResponseCode(400);
        $this->setResponseBody(['created' => false, 'reason' => $this->getReason(), 'code' => $this->getCode()]);
    }

    /**
     * Gets the EventInterface instance associated with this Exception
     *
     * @return Cygnus\OlyticsBundle\Model\Event\EventInterface
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * Gets the 'friendly' reason for the Exception, for use in JSON responses
     * Takes the base Exception class name and strips 'Exception' from the name
     *
     * @return string
     */
    public function getReason()
    {
        $classParts = explode('\\', get_class($this));
        $baseClass = array_pop($classParts);
        return str_replace('Exception', '', $baseClass);
    }
}
