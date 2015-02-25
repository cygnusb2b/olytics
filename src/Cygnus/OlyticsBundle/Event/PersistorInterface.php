<?php
namespace Cygnus\OlyticsBundle\Event;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;

interface PersistorInterface
{
    public function persist(EventInterface $event, array $entities, $app, $account, $product);
}
