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

    protected $application;

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
     * Sets the application key for this request.
     *
     * @param  string   $application
     * @return self
     */
    public function setApplication($application)
    {
        if (is_string($application)) {
            $this->application = $application;
        }
        return $this;
    }

    /**
     * Determines if there is an application key for this request.
     *
     * @return bool
     */
    public function hasApplication()
    {
        return is_string($this->getApplication());
    }

    /**
     * Gets the application key for this request.
     *
     * @return string|null
     */
    public function getApplication()
    {
        return $this->application;
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
