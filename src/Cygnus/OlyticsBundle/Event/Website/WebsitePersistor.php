<?php
namespace Cygnus\OlyticsBundle\Event\Website;

use Cygnus\OlyticsBundle\Event\Persistor;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;
use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Model\Session\WebsiteSession;
use Cygnus\OlyticsBundle\Model\Event\WebsiteEvent;
use Doctrine\MongoDB\Query\Builder;
use Doctrine\Common\Util\Inflector;
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

        // Persist to DB
        $this->persistEvent($event);
    }

    /**
     * Ensures that the account and product (group) are valid
     *
     * @return void
     * @throws \RuntimeException If the product (group) is invalid
     */
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
        $this->getIndexManager()->createIndexes('event_ttl', $this->getDatabaseName(), $this->getEventCollection($event->getEntity()));

        $insertObj = [
            'action'    => $event->getAction(),
            'clientId'  => $event->getEntity()->getClientId(),
            'createdAt' => new MongoDate(time()),
            'entity'    => [
                'keyValues'     => $event->getEntity()->getKeyValues(),
            ],
            'session'   => [
                'id'            => new MongoBinData($event->getSession()->getId(), MongoBinData::UUID),
                'customerId'    => $event->getSession()->getCustomerId(),
                'visitorId'     => new MongoBinData($event->getSession()->getVisitorId(), MongoBinData::UUID),
                'env'           => $event->getSession()->getEnv(),
                'ip'            => $event->getSession()->getIp(),
                'ua'            => $event->getSession()->getUa(),
            ],
        ];

        $relatedTo = $event->getEntity()->getRelatedTo();

        // $addToSet ensures that previously set relatedTo arrays are not overwritten
        // Drawback: relatedTo removals will not be reflected
        if (!empty($relatedTo)) {
            $insertObj['entity']['relatedTo'] = [];
            foreach ($relatedTo as $relatedEntity) {
                $insertObj['entity']['relatedTo'][] = [
                    'type'      => $relatedEntity->getType(),
                    'clientId'  => $relatedEntity->getClientId(),
                    'relFields' => $relatedEntity->getRelFields(),
                ];
            }
        }


        $eventData = $event->getData();

        if (!empty($eventData)) {
            $insertObj['data'] = $eventData;
        }

        $relatedEntities = $event->getRelatedEntities();
        if (!empty($relatedEntities)) {
            foreach ($relatedEntities as $entity) {
                $keyValues = $entity->getKeyValues();
                if (empty($keyValues)) {
                    continue;
                }
                $type = Inflector::camelize($entity->getType());
                $insertObj['relatedEntities'][$type] = $keyValues;
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
     * Gets the Event collection name
     *
     * @param  Entity   $entity
     * @return string
     */
    protected function getEventCollection(Entity $entity)
    {
        return $entity->getType();
    }

    /**
     * Gets the Olytics database name
     *
     * @return string
     */
    protected function getDatabaseName()
    {
        return sprintf('oly_%s_%s_events', $this->account, $this->product);
    }
}