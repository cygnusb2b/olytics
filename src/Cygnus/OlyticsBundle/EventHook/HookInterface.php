<?php

namespace Cygnus\OlyticsBundle\EventHook;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;

interface HookInterface
{
    /**
     * Executes the Event modification hook
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return Cygnus\OlyticsBundle\Model\Event\EventInterface
     */
    public function execute(EventInterface $event, $accountKey, $groupKey);

    /**
     * Determines if this hook supports the provided event, account, and group
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return bool
     */
    public function supports(EventInterface $event, $accountKey, $groupKey);
}