<?php

namespace Cygnus\OlyticsBundle\Aggregation;

use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class OpenXUserAggregation extends AbstractAggregation
{
    /**
     * Gets the index mapping for this aggregation
     *
     * @return array
     */
    public function getIndexes()
    {
        return [
            ['keys' => ['start' => 1, 'action' => 1, 'adId' => 1, 'userId' => 1], 'options' => ['unique' => true]],
            ['keys' => ['lastInsert' => -1], 'options' => []],
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
        // Define the db and collection name where this aggregation should be stored
        $dbName = 'openx_user_feed';
        $collName = sprintf('%s_%s', $accountKey, $groupKey);

        // Create the indexes for the collection (if they don't exist)
        $this->createIndexes($dbName, $collName);

        // Format the user id and month for Mongo
        $customerId = $event->getSession()->getCustomerId();
        $userId = ($customerId instanceof \MongoId) ? $customerId : new \MongoId($customerId);
        $start  = new \MongoDate(strtotime($event->getCreatedAt()->format('Y-m-01 00:00:00')));

        // Create the initial query builder that will handle the data upsert/aggregation
        $builder = $this->createQueryBuilder($dbName, $collName)
            ->update()
            ->upsert(true)
            ->field('start')->equals($start)
            ->field('action')->equals($event->getAction())
            ->field('adId')->equals($event->getEntity()->getClientId())
            ->field('userId')->equals($userId)
        ;

        // Define the object to send to MongoDB
        $newObj = [
            '$setOnInsert'  => [
                'start'         => $start,
                'action'        => $event->getAction(),
                'adId'          => $event->getEntity()->getClientId(),
                'userId'        => $userId,
            ],
            '$set'          => [
                'lastInsert'    => new \MongoDate($event->getCreatedAt()->getTimestamp()),
            ],
            '$inc'          => [
                'actions'       => 1,
            ],
        ];

        // Execute the upsert
        $builder
            ->setNewObj($newObj)
            ->getQuery()
            ->execute()
        ;
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
            // Aggregation is disabled for account or group
            return false;
        }

        if ('ad' !== $event->getEntity()->getType()) {
            // Not an ad event
            return false;
        }

        $customerId = $event->getSession()->getCustomerId();
        if (empty($customerId)) {
            // No customer id on the event (anonymous)
            return false;
        }

        if ($customerId instanceof \MongoId) {
            return true;
        }

        try {
            // Valid MongoId
            $customerId = new \MongoId($customerId);
            return true;
        } catch (\Exception $e) {
            // Invalid MongoId
            return false;
        }
    }
}