<?php

namespace Cygnus\OlyticsBundle\Aggregation\Brevity;

use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class ShareAggregation extends AbstractAggregation
{
    /**
     * Gets the index mapping for this aggregation
     *
     * @return array
     */
    public function getIndexes()
    {
        return [
            ['keys' => ['issueId' => 1, 'customerId' => 1, 'provider' => 1, 'entity' => 1], 'options' => ['unique' => true]],
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
        $collName = 'Shares';

        $this->createIndexes($dbName, $collName);

        $upsertObj = $this->createUpsertObject($event);

        $builder = $this->createQueryBuilder($dbName, $collName)
            ->update()
            ->upsert(true)
            ->setNewObj($upsertObj)
        ;

        foreach (['issueId', 'customerId', 'provider', 'entity'] as $field) {
            $builder->field($field)->equals($upsertObj['$setOnInsert'][$field]);
        }

        $builder->getQuery()->execute();
        return $this;
    }

    protected function createUpsertObject(EventInterface $event)
    {
        $campaign = $event->getSession()->getCampaign();

        $eventData = $event->getData();
        $provider = isset($eventData['provider']) ? $eventData['provider'] : 'Other';

        return [
            '$setOnInsert'  => [
                'issueId'       => $campaign['content'],
                'customerId'    => $event->getSession()->getCustomerId(),
                'provider'      => $provider,
                'entity'        => [
                    'type'      => $event->getEntity()->getType(),
                    'clientId'  => $event->getEntity()->getClientId(),
                ],
                'firstShare'    => new \MongoDate($event->getCreatedAt()->getTimestamp()),
            ],
            '$set'          => [
                'lastShare'    => new \MongoDate($event->getCreatedAt()->getTimestamp()),
            ],
            '$inc'          => [
                'shares'      => 1,
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

        return $event->getAction() === 'share';
    }
}
