<?php
namespace Cygnus\OlyticsBundle\Event\Website;

use Cygnus\OlyticsBundle\Event\Request;
use Symfony\Component\HttpFoundation\ParameterBag;

class WebsiteRequest extends Request
{
    /**
     * The session data for this request
     *
     * @var ParameterBag
     */
    protected $session;

    /**
     * Flags whether the customer ID should be appending to previous sessions
     *
     * @var bool
     */
    public $appendCustomer;

    /**
     * Creates a new WebsiteRequest
     *
     * @param  array  $session  The session data
     * @param  array  $event    The event data
     * @param  string $vertical The vertical
     * @param  string $product  The product
     * @return void
     */
    public function __construct(array $session, array $event, $vertical, $product, $appendCustomer = false)
    {
        $this->vertical = $vertical;
        $this->product  = $product;
        $this->appendCustomer = $appendCustomer;

        $this->setSession($session)->setEvent($event);
    }

    /**
     * Sets the session data
     *
     * @param  array $session
     * @return self
     */
    public function setSession(array $session)
    {
        $this->session = new ParameterBag($session);
        return $this;
    }

    /**
     * Gets the session data
     *
     * @return ParameterBag
     */
    public function getSession()
    {
        return $this->session;
    }

}