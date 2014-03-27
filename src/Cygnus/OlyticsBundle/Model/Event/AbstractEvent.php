<?php
namespace Cygnus\OlyticsBundle\Model\Event;

use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Types\DateType;
use \DateTime;

abstract class AbstractEvent implements EventInterface
{

    /**
     * The event action, e.g. view, click, share
     *
     * @var string
     */
    protected $action;

    /**
     * The event metadata entity
     *
     * @var Cygnus\OlyticsBundle\Model\Metadata\Entity
     */
    protected $entity;

    /**
     * Event created at date
     *
     * @var DateTime
     */
    protected $createdAt;

    /**
     * Additional data about the event
     *
     * @var array
     */
    protected $data = array();

    public function isValid()
    {
        if (!empty($this->entity)) {
            return (!empty($this->action) && $this->entity->isValid());
        } else {
            return false;
        }
    }


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setCreatedAt(new DateTime());
    }

    /**
     * Set action
     *
     * @param string $action
     * @return self
     */
    public function setAction($action)
    {
        $this->action = strtolower($action);
        return $this;
    }

    /**
     * Get action
     *
     * @return string $action
     */
    public function getAction()
    {
        return $this->action;
    }



    /**
     * Remove entity
     *
     * @param Cygnus\OlyticsBundle\Model\Metadata\Entity $entity
     * @return self
     */
    public function setEntity(Entity $entity)
    {
        $this->entity = $entity;
        return $this;
    }

    /**
     * Get entity
     *
     * @return Cygnus\OlyticsBundle\Model\Metadata\Entity $entity
     */
    public function getEntity()
    {
        return $this->entity;
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

    /**
     * Set data
     *
     * @param hash $data
     * @return self
     */
    public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    public function getData()
    {
        return $this->data;
    }
}
