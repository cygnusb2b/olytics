<?php
namespace Cygnus\OlyticsBundle\Model\Event;

use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Types\DateType;
use \DateTime;

interface EventInterface
{
    public function setAction($action);
    public function getAction();
    public function setEntity(Entity $entity);
    public function getEntity();
}