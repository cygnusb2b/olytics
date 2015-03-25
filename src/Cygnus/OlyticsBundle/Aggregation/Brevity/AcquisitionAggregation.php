<?php

namespace Cygnus\OlyticsBundle\Aggregation\Brevity;

use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class AcquisitionAggregation extends AbstractAggregation
{
    /**
     * Gets the index mapping for this aggregation
     *
     * @return array
     */
    public function getIndexes()
    {
        return [
            ['keys' => ['issueId' => 1, 'customerId' => 1, 'source' => 1, 'medium' => 1], 'options' => ['unique' => true]],
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
    protected function doExecute(EventInterface $event, $accountKey, $groupKey, $appKey)
    {
        $dbName = $this->getDbName($accountKey, $groupKey);
        $collName = 'Acquisition';

        $this->createIndexes($dbName, $collName);

        $upsertObj = $this->createUpsertObject($event);

        $builder = $this->createQueryBuilder($dbName, $collName)
            ->update()
            ->upsert(true)
            ->setNewObj($upsertObj)
        ;

        foreach (['issueId', 'customerId', 'source', 'medium'] as $field) {
            $builder->field($field)->equals($upsertObj['$setOnInsert'][$field]);
        }

        $builder->getQuery()->execute();
        return $this;
    }

    protected function createUpsertObject(EventInterface $event)
    {
        $campaign = $event->getSession()->getCampaign();

        return [
            '$setOnInsert'  => [
                'issueId'       => $campaign['content'],
                'customerId'    => $event->getSession()->getCustomerId(),
                'source'        => $campaign['source'],
                'medium'        => $campaign['medium'],
                'acquired'      => new \MongoDate($event->getCreatedAt()->getTimestamp()),
            ],
        ];
    }

    /**
     * Determines if this aggregation supports the provided event, account, and group
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return bool
     */
    public function supports(EventInterface $event, $accountKey, $groupKey, $appKey)
    {
        if (false === parent::supports($event, $accountKey, $groupKey, $appKey)) {
            return false;
        }

        $campaign = $event->getSession()->getCampaign();
        if (!isset($campaign['source']) || !isset($campaign['medium']) || !isset($campaign['content'])) {
            return false;
        }
        return true;
    }
}
