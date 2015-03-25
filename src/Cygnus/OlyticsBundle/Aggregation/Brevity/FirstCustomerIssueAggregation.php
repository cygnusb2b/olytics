<?php

namespace Cygnus\OlyticsBundle\Aggregation\Brevity;

use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class FirstCustomerIssueAggregation extends AbstractAggregation
{
    /**
     * Gets the index mapping for this aggregation
     *
     * @return array
     */
    public function getIndexes()
    {
        return [
            ['keys' => ['issueId' => 1, 'customerId' => 1], 'options' => ['unique' => true]],
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
        $collName = 'FirstCustomerIssues';

        $this->createIndexes($dbName, $collName);

        $upsertObj = $this->createUpsertObject($event);

        $builder = $this->createQueryBuilder($dbName, $collName)
            ->update()
            ->upsert(true)
            ->setNewObj($upsertObj)
        ;

        foreach (['issueId', 'customerId'] as $field) {
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
                'acquired'    => new \MongoDate($event->getCreatedAt()->getTimestamp()),
            ],
        ];
    }
}
