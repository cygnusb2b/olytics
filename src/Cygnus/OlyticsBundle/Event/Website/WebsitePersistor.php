<?php
namespace Cygnus\OlyticsBundle\Event\Website;

use Cygnus\OlyticsBundle\Event\Persistor;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;
use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Model\Session\WebsiteSession;
use Cygnus\OlyticsBundle\Model\Event\WebsiteEvent;
use Doctrine\MongoDB\Query\Builder;
use \MongoBinData;
use \MongoDate;

class WebsitePersistor extends Persistor
{

    protected $vertical;

    protected $product;

    public function persist(EventInterface $event, array $relatedEntities, $vertical, $product, $appendCustomer = false) {
        $this->product = $product;
        $this->vertical = $vertical;

        $this->persistEntities($event, $relatedEntities);
        $this->persistSession($event);
        $this->persistEvent($event);
    }

    protected function persistEntities(WebsiteEvent $event, array $relatedEntities)
    {
        $this->persistEntity($event->getEntity());

        foreach ($relatedEntities as $relatedEntity) {
            $this->persistEntity($relatedEntity);
        }
    }

    protected function createQueryBuilder($dbName, $collectionName)
    {
        $collection = $this->connection->selectCollection($dbName, $collectionName);
        return new Builder($collection);
    }

    protected function persistEvent(WebsiteEvent $event)
    {
        $dbName = 'olytics_cygnus_' . $this->vertical . '_' . $this->product; 
        $collectionName = 'event.' . $event->getEntity()->getType();

        $sessionId = new MongoBinData($event->getSession()->getId(), MongoBinData::UUID);

        $upsertEvent = array(
            'action'    => $event->getAction(),
            'clientId'  => $event->getEntity()->getClientId(),
            'sessionId' => $sessionId,
        );

        $eventData = $event->getData();

        if (!empty($eventData)) {
            $upsertEvent['data'] = $eventData;
        }

        $upsertObj = array(
            '$push'  => array(
                'occurredOn' => new MongoDate($event->getCreatedAt()->getTimestamp()),
            ),
            '$inc'  => array(
                'count'  => 1,
            ),
            '$setOnInsert'  => array(
                'event' => $upsertEvent,
            ),
        );

        $queryBuilder = $this->createQueryBuilder($dbName, $collectionName);

        $queryBuilder
            ->update()
            ->upsert(true)
            ->field('event')->equals($upsertEvent)
            ->setNewObj($upsertObj)
            ->getQuery()
            ->execute();
    }

    protected function persistSession(WebsiteEvent $event)
    {
        $dbName = 'olytics_cygnus_' . $this->vertical . '_' . $this->product; 
        $session = $event->getSession();

        $sessionId = new MongoBinData($session->getId(), MongoBinData::UUID);
        $eventCreated = new MongoDate($event->getCreatedAt()->getTimestamp());
        $eventCountKey = 'events.' . $event->getEntity()->getType();

        $upsertObj = array(
            '$set'  => array(
                'lastEvent' => $eventCreated,
            ),
            '$inc'  => array(
                'events.total'  => 1,
                $eventCountKey  => 1,
            ),
            '$setOnInsert'  => array(
                'firstEvent'    => $eventCreated,
                'sessionId'     => $sessionId,
                'visitorId'     => new MongoBinData($session->getVisitorId(), MongoBinData::UUID),
                'customerId'    => $session->getCustomerId(),
                'ip'            => $session->getIp(),
                'ua'            => $session->getUa(),
            ),
        );

        $queryBuilder = $this->createQueryBuilder($dbName, 'session');

        $queryBuilder
            ->update()
            ->upsert(true)
            ->field('sessionId')->equals($sessionId)
            ->setNewObj($upsertObj)
            ->getQuery()
            ->execute();
    }

    protected function persistEntity(Entity $entity)
    {

        $dbName = 'olytics_cygnus_' . $this->vertical;
        $collectionName = 'entity.' . $entity->getType();

        // @todo Create these as annotations on the classes themselves
        $upsertObj = array(
            '$set'  => array(
                'clientId'  => $entity->getClientId(),
                'updatedAt' => new MongoDate($entity->getUpdatedAt()->getTimestamp()),
            ),
        );
        $keyValues = $entity->getKeyValues();

        if (!empty($keyValues)) {
            foreach ($keyValues as $key => $value) {
                if ($value instanceof \DateTime) {
                    $upsertObj['$set']['keyValues'][$key] = new MongoDate($value->getTimestamp());
                } else {
                    $upsertObj['$set']['keyValues'][$key] = $value;
                }
            }
        }

        $relatedTo = $entity->getRelatedTo();

        if (!empty($relatedTo)) {
            foreach ($relatedTo as $relatedEntity) {
                $upsertObj['$set']['relatedTo'][] = array(
                    'type'      => $relatedEntity->getType(),
                    'clientId'  => $relatedEntity->getClientId(),
                    'relFields' => $relatedEntity->getRelFields(),
                );
            }
        }

        $queryBuilder = $this->createQueryBuilder($dbName, $collectionName);

        $queryBuilder
            ->update()
            ->upsert(true)
            ->field('clientId')->equals($entity->getClientId())
            ->setNewObj($upsertObj)
            ->getQuery()
            ->execute();


    }
}