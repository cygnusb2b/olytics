<?php

namespace Cygnus\OlyticsBundle\Aggregation;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class AdImpressionAggregation extends AbstractAggregation
{
    /**
     * Gets the index mapping for this aggregation
     *
     * @return array
     */
    public function getIndexes()
    {
        return [
            ['keys' => ['metadata' => 1], 'options' => ['unique' => true]],
            ['keys' => ['lastInsert' => -1], 'options' => []],
            ['keys' => ['metadata.start' => 1], 'options' => []],
        ];
    }

    /**
     * Executes the aggregation
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return self
     */
    protected function doExecute(EventInterface $event, $accountKey, $groupKey)
    {
        return $this;
    }

    /**
     * Determines if this aggregation supports the provided event, account, and group
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return bool
     */
    public function supports(EventInterface $event, $accountKey, $groupKey)
    {
        if (!$this->isEnabled($accountKey, $groupKey)) {
            return false;
        }

        return 'ad' === $event->getEntity()->getType();
    }
}