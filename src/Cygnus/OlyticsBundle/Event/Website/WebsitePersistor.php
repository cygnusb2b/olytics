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
use Cygnus\OlyticsBundle\Exception\Model\InvalidModelException;

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

    /**
     * The Application key
     *
     * @var string|null
     */
    protected $app;

    /**
     * Persists an event, it's session, and it's related entities to the database
     *
     * @param  EventInterface   $event
     * @param  array            $relatedEntities
     * @param  string           $app
     * @param  string           $account
     * @param  string           $product
     * @param  boolean          $appendCustomer
     * @return void
     * @todo   Need to determine how to store relatedEntities on the event directly...
     */
    public function persist(EventInterface $event, array $relatedEntities, $app, $account, $product, $appendCustomer = false)
    {
        $this->account = strtolower($account);
        $this->product = strtolower($product);
        $this->app = is_string($app) ? $app : null;

        // Ensure account and product exists
        $this->validateProduct();

        // Validate the event
        $this->getEventValidator()->validate($event);

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
     * Persists the Event to the database
     *
     * @todo   This should dispatch an event once completed that the aggregations and event modification hooks can listen for. For now just run manually.
     * @param  WebsiteEvent   $event
     * @return void
     */
    protected function persistEvent(WebsiteEvent $event)
    {
        // Execute event modification hooks
        $event = $this->getEventHookManager()->executeAll($event, $this->account, $this->product);

        $indexes = $this->getIndexManager()->indexFactoryMulti($this->getEventIndexes());
        $this->getIndexManager()->createIndexes($indexes, $this->getDatabaseName(), $this->getEventCollection($event->getEntity()));

        $insertObj = $this->createInsertObject($event);

        $queryBuilder = $this->createQueryBuilder($this->getDatabaseName(), $this->getEventCollection($event->getEntity()));

        $queryBuilder
            ->insert()
            ->setNewObj($insertObj)
            ->getQuery()
            ->execute()
        ;
        // Execute aggregations
        $this->getAggregationManager()->executeAll($event, $this->account, $this->product, $this->app);
    }

    /**
     * Creates the object to insert into the database
     *
     * @param  WebsiteEvent   $event
     * @return void
     */
    protected function createInsertObject(WebsiteEvent $event)
    {
         $insertObj = [
            'action'    => $event->getAction(),
            'clientId'  => $event->getEntity()->getClientId(),
            'createdAt' => new MongoDate(time()),
            'entity'    => [
                'keyValues'     => $event->getEntity()->getKeyValues(),
            ],
            'session'   => [
                'id'            => new MongoBinData($event->getSession()->getId(), MongoBinData::UUID),
                'campaign'      => $event->getSession()->getCampaign(),
                'customerId'    => $event->getSession()->getCustomerId(),
                'rcid'          => $event->getSession()->getRcid(),
                'visitorId'     => new MongoBinData($event->getSession()->getVisitorId(), MongoBinData::UUID),
                'env'           => $event->getSession()->getEnv(),
                'ip'            => $event->getSession()->getIp(),
                'ua'            => $event->getSession()->getUa(),
            ],
        ];

        $relatedTo = $event->getEntity()->getRelatedTo();

        if (!$relatedTo->isEmpty()) {
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
        return $insertObj;
    }

    /**
     * Gets the index mapping for the event collection
     *
     * @return array
     */
    protected function getEventIndexes()
    {
        return [
            ['keys' => ['clientId' => 1, 'action' => 1], 'options' => []],
            ['keys' => ['createdAt' => 1], 'options' => ['expireAfterSeconds' => 60*60*24*30]],
            ['keys' => ['session.id' => 1, 'session.customerId' => 1, 'session.visitorId' => 1], 'options' => []],
        ];
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
        $dbKey = sprintf('%s_%s', $this->account, $this->product);
        if (null !== $this->app) {
            $dbKey .= '_'.$this->app;
        }
        return sprintf('oly_%s_events', $dbKey);
    }
}
