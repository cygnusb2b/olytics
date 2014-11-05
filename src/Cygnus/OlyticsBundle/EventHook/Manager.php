<?php

namespace Cygnus\OlyticsBundle\EventHook;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class Manager
{
    /**
     * Contains all registered Event modification hooks
     *
     * @return array<Cygnus\OlyticsBundle\EventHook\HookInterface>
     */
    protected $eventHooks = [];

    /**
     * Adds a registered Event modification hook
     *
     * @param  Cygnus\OlyticsBundle\EventHook\HookInterface  $eventHook
     * @return self
     */
    public function addEventHook(HookInterface $eventHook)
    {
        $this->eventHooks[get_class($eventHook)] = $eventHook;
        return $this;
    }

    /**
     * Gets all registered Event modification hooks
     *
     * @return array<Cygnus\OlyticsBundle\EventHook\HookInterface>
     */
    public function getEventHooks()
    {
        return $this->eventHooks;
    }

    /**
     * Executes all registered Event modification hooks
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return self
     */
    public function executeAll(EventInterface $event, $accountKey, $groupKey)
    {
        foreach ($this->getEventHooks() as $eventHook) {
            $event = $eventHook->execute($event, $accountKey, $groupKey);
        }
        return $event;
    }
}