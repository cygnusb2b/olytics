<?php

namespace Cygnus\OlyticsBundle\Aggregation;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;

interface AggregationInterface
{
    /**
     * Gets the index mapping for this aggregation
     *
     * @return array
     */
    public function getIndexes();

    /**
     * Executes the aggregation
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return self
     */
    public function execute(EventInterface $event, $accountKey, $groupKey);

    /**
     * Determines if this aggregation supports the provided event, account, and group
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return bool
     */
    public function supports(EventInterface $event, $accountKey, $groupKey);
}