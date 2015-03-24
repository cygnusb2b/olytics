<?php

namespace Cygnus\OlyticsBundle\Aggregation\Brevity;

use Doctrine\Common\Inflector\Inflector;
use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class SessionAggregation extends AbstractAggregation
{
    /**
     * Gets the index mapping for this aggregation
     *
     * @return array
     */
    public function getIndexes()
    {
        return [
            ['keys' => ['issueId' => 1, 'customerId' => 1, 'sessionId' => 1, 'entity.type' => 1, 'entity.clientId' => 1], 'options' => ['unique' => true]],
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
        $collName = 'IssueSessions';

        $this->createIndexes($dbName, $collName);

        $campaign = $event->getSession()->getCampaign();
        $entityType = $event->getEntity()->getType();

        if (!in_array($entityType, ['video', 'brevity/story', 'brevity/issue'])) {
            return $this;
        }

        if ('video' === $entityType) {
            $entity = $event->getRelatedEntities()[0];
            $action = sprintf('video.%s', $event->getAction());
        } else {
            $entity = $event->getEntity();
            $action = $event->getAction();
        }

        $upsertObj = [
            '$setOnInsert'  => [
                'issueId'           => $campaign['content'],
                'customerId'        => $event->getSession()->getCustomerId(),
                'sessionId'         => new \MongoBinData($event->getSession()->getId(), \MongoBinData::UUID),
                'entity.type'       => $entity->getType(),
                'entity.clientId'   => $entity->getClientId(),
                'firstAction'       => new \MongoDate($event->getCreatedAt()->getTimestamp()),
            ],
            '$set'          => [
                'lastAction'        => new \MongoDate($event->getCreatedAt()->getTimestamp()),
                'entity.keyValues'  => $entity->getKeyValues(),
            ],
        ];

        if ('video' === $entityType && 'duration' === $event->getAction()) {
            $upsertObj['$inc']['duration.video'] = $event->getData()['duration'];
        }

        switch ($action) {
            case 'focusin':
                $upsertObj['$inc']['duration.idle'] = $event->getData()['idleSeconds'];
                break;
            case 'scroll':
                $upsertObj['$max']['maxScrollPercent'] = $event->getData()['percent'];
                break;
            default:
                break;
        }

        $actionsField = sprintf('actions.%s', $action);

        $upsertObj['$inc'][$actionsField] = 1;
        $upsertObj['$inc']['actions._total'] = 1;

        $database = $this->connection->selectDatabase($dbName);

        $command = array(
            'findandmodify' => $collName,
            'query'         => [],
            'update'        => $upsertObj,
            'upsert'    => true,
            'new'       => true,
        );

        foreach ($this->getIndexes()[0]['keys'] as $field => $direction) {
            $command['query'][$field] = $upsertObj['$setOnInsert'][$field];
        }

        $result = $database->command($command);

        $activeDuration = $totalDuration = $result['value']['lastAction']->sec - $result['value']['firstAction']->sec;
        if (isset($result['value']['duration']['idle'])) {
            $activeDuration -= $result['value']['duration']['idle'];
        }

        // Now update the session duration
        $builder = $this->createQueryBuilder($dbName, $collName)
            ->update()
            ->field('_id')->equals($result['value']['_id'])
            ->field('duration.active')->set($activeDuration)
            ->field('duration.total')->set($totalDuration)
            ->getQuery()
            ->execute();
        ;


        var_dump($result);
        die();



        // var_dump(json_encode($upsertObj));
        // die();



        $builder = $this->createQueryBuilder($dbName, $collName)
            ->findAndUpdate()
            ->upsert(true)
            ->returnNew(true)
            ->setNewObj($upsertObj)
        ;



        $result = $builder->getQuery()->execute();

        var_dump($result);
        die();



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

        return true;
        // return $event->getAction() === 'share';
    }
}
