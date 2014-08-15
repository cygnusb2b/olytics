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
     * Flags whether the customerID should be appended to previous sessions
     *
     * @var bool
     */
    public $appendCustomer;

    /**
     * Creates a new WebsiteRequest
     *
     * @param  array  $session  The session data
     * @param  array  $event    The event data
     * @param  string $account  The account
     * @param  string $product  The product
     * @param  bool   $appendCustomer
     * @return void
     */
    public function __construct(array $session, array $event, $account, $product, $appendCustomer = false)
    {
        $this->account = $account;
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