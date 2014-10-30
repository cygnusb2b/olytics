<?php

namespace Cygnus\OlyticsBundle\Aggregation;

use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class ContentArchiveAggregation extends AbstractAggregation
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
            ['keys' => ['metadata.month' => 1, 'metadata.contentId' => 1, 'metadata.userId' => 1], 'options' => []],
            ['keys' => ['lastAccessed' => 1], 'options' => []],
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
        $dbName = 'content_traffic_archive';
        $collName = sprintf('%s_%s', $accountKey, $groupKey);

        $this->createIndexes($dbName, $collName);

        $userId = $event->getSession()->getCustomerId();
        if (empty($userId) || !$userId) {
            $userId = null;
        } else {
            try {
                $userId = new \MongoId($userId);
            } catch (\Exception $e) {
                $userId = null;
            }
        }

        $metadata = [
            'contentId' => $event->getEntity()->getClientId(),
            'month'     => new \MongoDate(strtotime($event->getCreatedAt()->format('Y-m-01 00:00:00'))),
            'sessionId' => new \MongoBinData($event->getSession()->getId(), \MongoBinData::UUID),
        ];

        if (null !== $userId) {
            $metadata['userId'] = $userId;
        }

        $upsertObj = [
            '$setOnInsert'  => [
                'metadata'  => $metadata,
                'visits'    => 1,
            ],
            '$set'          => [
                'lastAccessed'  => new \MongoDate($event->getCreatedAt()->getTimestamp()),
            ],
            '$inc'          => [
                'pageviews' => 1,
            ],
        ];

        $builder = $this->createQueryBuilder($dbName, $collName)
            ->update()
            ->upsert(true)
            ->field('metadata')->equals($metadata)
            ->setNewObj($upsertObj)
            ->getQuery()
            ->execute();
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
        return false;
        if (!$this->isEnabled($accountKey, $groupKey)) {
            return false;
        }

        return 'content' === $event->getEntity()->getType();
    }
}