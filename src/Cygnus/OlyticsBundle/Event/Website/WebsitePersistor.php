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

        if ($appendCustomer === true) {
            $this->appendCustomer($event);
        }
    }

    protected function persistEntities(WebsiteEvent $event, array $relatedEntities)
    {
        $this->persistEntity($event->getEntity());

        foreach ($event->getRelatedEntities() as $relatedEntity) {
            $this->persistEntity($relatedEntity);
        }

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

        // Persist the related entities

        $upsertEvent = array(
            'action'    => $event->getAction(),
            'clientId'  => $event->getEntity()->getClientId(),
            'sessionId' => $sessionId,
        );

        $eventData = $event->getData();

        if (!empty($eventData)) {
            $upsertEvent['data'] = $eventData;
        }

        $relatedEntities = $event->getRelatedEntities();
        if (!empty($relatedEntities)) {
            foreach ($relatedEntities as $entity) {
                $upsertEvent['relatedEntities'][] = array(
                    'type'      => $entity->getType(),
                    'clientId'  => $entity->getClientId(),
                );
            }
            
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

    protected function appendCustomer(WebsiteEvent $event)
    {
        $dbName = 'olytics_cygnus_' . $this->vertical . '_' . $this->product; 
        $session = $event->getSession();

        $visitorId = new MongoBinData($session->getVisitorId(), MongoBinData::UUID);

        $upsertObj = array(
            '$set'  => array(
                'customerId' => $session->getCustomerId(),
            ),
        );

        $queryBuilder = $this->createQueryBuilder($dbName, 'session');

        $queryBuilder
            ->update()
            ->multiple(true)
            ->field('visitorId')->equals($visitorId)
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
        $sessionCreated = new MongoDate($session->getCreatedAt()->getTimestamp());
        $eventCountKey = 'events.' . $event->getEntity()->getType();

        // Removing session time, because $sessionCreated is not reliable from the front-end
        // $sessionTime = $event->getCreatedAt()->getTimestamp() - $session->getCreatedAt()->getTimestamp();

        $upsertObj = array(
            '$set'  => array(
                'lastEvent' => $eventCreated,
            ),
            '$inc'  => array(
                'events.total'  => 1,
                //'time'          => $sessionTime,
                $eventCountKey  => 1,
            ),
            '$setOnInsert'  => array(
                'createdAt'     => new MongoDate(),
                'sessionId'     => $sessionId,
                'visitorId'     => new MongoBinData($session->getVisitorId(), MongoBinData::UUID),
                'customerId'    => $session->getCustomerId(),
                'ip'            => $session->getIp(),
                'ua'            => $session->getUa(),
                'env'           => $session->getEnv(),
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
                // Specifiying keyValues.key ensures that Mongo won't remove key/value pairs 'accidentally'
                // e.g. if a persisted object has key1, key2, key3, but this object only has key1 and key2
                // This update logic will ensure that key3 remains on the object
                if ($value instanceof \DateTime) {
                    $upsertObj['$set']['keyValues.' . $key] = new MongoDate($value->getTimestamp());
                } else {
                    $upsertObj['$set']['keyValues.' . $key] = $value;
                }
            }
        }

        $relatedTo = $entity->getRelatedTo();

        // $addToSet ensures that previously set relatedTo arrays are not overwritten
        // Drawback: relatedTo removals will not be reflected
        if (!empty($relatedTo)) {
            $upsertObj['$addToSet']['relatedTo']['$each'] = array();
            foreach ($relatedTo as $relatedEntity) {
                $upsertObj['$addToSet']['relatedTo']['$each'][] = array(
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