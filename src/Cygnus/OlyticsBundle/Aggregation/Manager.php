<?php

namespace Cygnus\OlyticsBundle\Aggregation;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class Manager
{
    /**
     * Contains all registered Aggregations
     *
     * @return array<Cygnus\OlyticsBundle\Aggregation\AggregationInterface>
     */
    protected $aggregations = [];

    /**
     * Adds a registered Aggregation
     *
     * @param  Cygnus\OlyticsBundle\Aggregation\AggregationInterface  $aggregation
     * @return self
     */
    public function addAggregation(AggregationInterface $aggregation)
    {
        $this->aggregations[get_class($aggregation)] = $aggregation;
        return $this;
    }

    /**
     * Gets all registered Aggregations
     *
     * @return array<Cygnus\OlyticsBundle\Aggregation\AggregationInterface>
     */
    public function getAggregations()
    {
        return $this->aggregations;
    }

    /**
     * Executes all registered Aggregations
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return self
     */
    public function executeAll(EventInterface $event, $accountKey, $groupKey)
    {
        foreach ($this->getAggregations() as $aggregation) {
            $aggregation->execute($event, $accountKey, $groupKey);
        }
        return $this;
    }
}