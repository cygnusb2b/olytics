<?php
namespace Cygnus\OlyticsBundle\Model\Session;

use \DateTime;
use Cygnus\OlyticsBundle\Types\DateType;

abstract class AbstractSession
{
    /**
     * Session created at date
     *
     * @var DateTime
     */
    protected $createdAt;

    public function __construct()
    {
        $this->setCreatedAt(new DateTime());
    }

    /**
     * Set createdAt
     *
     * @param mixed $createdAt
     * @return self
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = DateType::convert($createdAt);
        return $this;
    }

    /**
     * Get createdAt
     *
     * @return DateTime $createdAt
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
