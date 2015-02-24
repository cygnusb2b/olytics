<?php
namespace Cygnus\OlyticsBundle\Model\Session;

use Cygnus\OlyticsBundle\Types\UUIDType;
use Cygnus\OlyticsBundle\Model\Exception\InvalidSessionException;

class WebsiteSession extends AbstractSession
{
    /**
     * Session UUID
     *
     * @var binary
     */
    protected $id;

    /**
     * Visitor UUID
     *
     * @var binary
     */
    protected $visitorId;

    /**
     * Encrypted Customer ID
     *
     * @var string
     */
    protected $customerId;

    /**
     * Referring Customer ID
     *
     * @var mixed
     */
    protected $rcid;

    /**
     * Enviroment variables, such as timezone and screen resolution
     *
     * @var array
     */
    protected $env = array();

    /**
     * IP Address associated with the session
     *
     * @var string
     */
    protected $ip;

    /**
     * User Agent associated with the session
     *
     * @var string
     */
    protected $ua;

    /**
     * Set visitorId
     *
     * @param string $visitorId
     * @return self
     */
    public function setVisitorId($visitorId)
    {
        $this->visitorId = UUIDType::convert($visitorId);
        return $this;
    }

    /**
     * Get visitorId
     *
     * @return binary $visitorId
     */
    public function getVisitorId()
    {
        return $this->visitorId;
    }

    /**
     * Set sessionId
     *
     * @param string $sessionId
     * @return self
     */
    public function setId($sessionId)
    {
        $this->id = UUIDType::convert($sessionId);
        return $this;
    }

    /**
     * Get sessionId
     *
     * @return binary $sessionId
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set customerId
     *
     * @param string $customerId
     * @return self
     */
    public function setCustomerId($customerId)
    {
        $this->customerId = $customerId;
        return $this;
    }

    /**
     * Get customerId
     *
     * @return string $customerId
     */
    public function getCustomerId()
    {
        return $this->customerId;
    }

    /**
     * Set the referring customer id
     *
     * @param  mixed    $rcid
     * @return self
     */
    public function setRcid($rcid)
    {
        $this->rcid = $rcid;
        return $this;
    }

    /**
     * Get the referring customer id
     *
     * @return mixed
     */
    public function getRcid()
    {
        return $this->rcid;
    }

    /**
     * Set env
     *
     * @param array $env
     * @return self
     */
    public function setEnv(array $env)
    {
        $this->env = $env;
        return $this;
    }

    /**
     * Get env
     *
     * @return array $env
     */
    public function getEnv()
    {
        return $this->env;
    }

    /**
     * Set ip
     *
     * @param string $ip
     * @return self
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * Get ip
     *
     * @return string $ip
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * Set ua
     *
     * @param string $ua
     * @return self
     */
    public function setUa($ua)
    {
        $this->ua = $ua;
        return $this;
    }

    /**
     * Get ua
     *
     * @return string $ua
     */
    public function getUa()
    {
        return $this->ua;
    }

}
