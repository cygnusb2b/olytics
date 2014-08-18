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
use \RuntimeException;
use \DateTime;

class WebsitePersistor extends Persistor
{
    /**
     * The Account key
     *
     * @var string
     */
    protected $account;

    /**
     * The Product key
     *
     * @var string
     */
    protected $product;

    protected $time;

    /**
     * Persists an event, it's session, and it's related entities to the database
     *
     * @param  EventInterface   $event
     * @param  array            $relatedEntities
     * @param  string           $account
     * @param  string           $product
     * @param  boolean          $appendCustomer
     * @return void
     */
    public function persist(EventInterface $event, array $relatedEntities, $account, $product, $appendCustomer = false) {

        $this->account = strtolower($account);
        $this->product = strtolower($product);

        $this->time = new DateTime();

        // Ensure account and product exists
        $this->validateProduct();

        $this->persistEntities($event, $relatedEntities);
        $this->persistSession($event);
        $this->persistEvent($event);

        if ($appendCustomer === true) {
            $this->appendCustomer($event);
        }
    }

    protected function validateProduct()
    {
        if (!isset($this->accounts[$this->account])) {
            throw new RuntimeException(sprintf('The account key "%s" is invalid.', $this->account));
        }
        $products = $this->accounts[$this->account]['products'];
        if (!in_array($this->product, $products)) {
            throw new RuntimeException(sprintf('The product key "%s" is invalid for account "%s"', $this->product, $this->account));
        }
    }

    /**
     * Persists metadata entities from an event to the database
     *
     * @param  WebsiteEvent   $event
     * @param  array          $relatedEntities
     * @return void
     */
    protected function persistEntities(WebsiteEvent $event, array $relatedEntities)
    {
        // Persist the primary event entity
        $this->persistEntity($event->getEntity());

        foreach ($event->getRelatedEntities() as $relatedEntity) {
            // Persist the additional related event entities
            $this->persistEntity($relatedEntity);
        }

        foreach ($relatedEntities as $relatedEntity) {
            $this->persistEntity($relatedEntity);
        }
    }

    /**
     * Creates a Builder instance for writing objects to the database
     *
     * @param  string   $dbName
     * @param  string   $collectionName
     * @return Builder
     */
    protected function createQueryBuilder($dbName, $collectionName)
    {
        $collection = $this->connection->selectCollection($dbName, $collectionName);
        return new Builder($collection);
    }

    /**
     * Gets the Event to the database
     *
     * @param  WebsiteEvent   $event
     * @return void
     */
    protected function persistEvent(WebsiteEvent $event)
    {
        $this->getIndexManager()->createIndexes('event', $this->getDatabaseName(), $this->getEventCollection($event->getEntity()));

        $sessionId = new MongoBinData($event->getSession()->getId(), MongoBinData::UUID);

        // Persist the related entities

        $insertObj = [
            'action'    => $event->getAction(),
            'clientId'  => $event->getEntity()->getClientId(),
            'sessionId' => $sessionId,
            'createdAt' => new MongoDate($event->getCreatedAt()->getTimestamp()),
        ];

        $eventData = $event->getData();

        if (!empty($eventData)) {
            $insertObj['data'] = $eventData;
        }

        $relatedEntities = $event->getRelatedEntities();
        if (!empty($relatedEntities)) {
            foreach ($relatedEntities as $entity) {
                $insertObj['relatedEntities'][] = array(
                    'type'      => $entity->getType(),
                    'clientId'  => $entity->getClientId(),
                );
            }

        }

        $queryBuilder = $this->createQueryBuilder($this->getDatabaseName(), $this->getEventCollection($event->getEntity()));

        $queryBuilder
            ->insert()
            ->setNewObj($insertObj)
            ->getQuery()
            ->execute()
        ;
    }

    /**
     * Appends perviously set sessions with a customerId
     *
     * @param  WebsiteEvent   $event
     * @return void
     */
    protected function appendCustomer(WebsiteEvent $event)
    {
        $session = $event->getSession();

        $visitorId = new MongoBinData($session->getVisitorId(), MongoBinData::UUID);

        $upsertObj = array(
            '$set'  => array(
                'customerId' => $session->getCustomerId(),
            ),
        );

        $queryBuilder = $this->createQueryBuilder($this->getDatabaseName(), $this->getSessionCollection());

        $queryBuilder
            ->update()
            ->multiple(true)
            ->field('visitorId')->equals($visitorId)
            ->setNewObj($upsertObj)
            ->getQuery()
            ->execute()
        ;
    }

    /**
     * Persists the Session to the database
     *
     * @param  WebsiteEvent   $event
     * @return void
     */
    protected function persistSession(WebsiteEvent $event)
    {
        $this->getIndexManager()->createIndexes('session', $this->getDatabaseName(), $this->getSessionCollection());

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
                'createdAt'     => $sessionCreated,
                'sessionId'     => $sessionId,
                'visitorId'     => new MongoBinData($session->getVisitorId(), MongoBinData::UUID),
                'customerId'    => $session->getCustomerId(),
                'ip'            => $session->getIp(),
                'ua'            => $session->getUa(),
                'env'           => $session->getEnv(),
            ),
        );

        $queryBuilder = $this->createQueryBuilder($this->getDatabaseName(), $this->getSessionCollection());

        $queryBuilder
            ->update()
            ->upsert(true)
            ->field('sessionId')->equals($sessionId)
            ->setNewObj($upsertObj)
            ->getQuery()
            ->execute()
        ;
    }

    /**
     * Persists an Entity to the database
     *
     * @param  Entity   $entity
     * @return void
     */
    protected function persistEntity(Entity $entity)
    {
        $this->getIndexManager()->createIndexes('entity', $this->getDatabaseName(), $this->getEntityCollection($entity));

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

        $queryBuilder = $this->createQueryBuilder($this->getDatabaseName(), $this->getEntityCollection($entity));

        $queryBuilder
            ->update()
            ->upsert(true)
            ->field('clientId')->equals($entity->getClientId())
            ->setNewObj($upsertObj)
            ->getQuery()
            ->execute()
        ;
    }

    /**
     * Gets the Entity collection name
     *
     * @param  Entity   $entity
     * @return string
     */
    protected function getEntityCollection(Entity $entity)
    {
        return sprintf('entity.%s', $entity->getType());
    }

    /**
     * Gets the Event collection name
     *
     * @param  Entity   $entity
     * @return string
     */
    protected function getEventCollection(Entity $entity)
    {
        return sprintf('event.%s.%s', $entity->getType(), $this->getQuarterSuffix());
    }

    /**
     * Gets the Event collection name
     *
     * @param  Entity   $entity
     * @return string
     */
    protected function getSessionCollection()
    {
        return sprintf('session.%s', $this->getQuarterSuffix());
    }

    protected function getQuarterSuffix()
    {
        return sprintf('%s_Q%s', $this->time->format('Y'), ceil($this->time->format('n') / 3));
    }

    protected function getEntityIndexes()
    {

    }

    /**
     * Gets the Olytics database name
     *
     * @return string
     */
    protected function getDatabaseName()
    {
        return sprintf('oly_%s_%s', $this->account, $this->product);
    }
}