<?php

namespace Cygnus\OlyticsBundle\EventHook;

use Doctrine\MongoDB\Connection;
use Doctrine\MongoDB\Query\Builder;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class MongoUserIdHook implements HookInterface
{
    /**
     * The MongoDB connection that contains the user collection for retrieving Mongo user ids
     *
     * @var Doctrine\MongoDB\Connection
     */
    protected $userConnection;

    /**
     * The cache client for retrieving Mongo user ids based on Omeda encypted ids
     *
     * @var Snc\RedisBundle\Client\Phpredis\Client
     */
    protected $cacheClient;

    /**
     * Executes the Event modification hook
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return Cygnus\OlyticsBundle\Model\Event\EventInterface
     */
    public function execute(EventInterface $event, $accountKey, $groupKey)
    {
        if (!$this->supports($event, $accountKey, $groupKey)) {
            return $event;
        }

        $customerId = $event->getSession()->getCustomerId();
        if (null === $customerId) {
            // Anonymous
            return $event;
        }

        if ($customerId instanceof \MongoId) {
            // Customer ID is already a MongoId
            return $event;
        }

        if (0 === stripos($customerId, 'id|me')) {
            // IDme ID. Do nothing.
            return $event;
        }

        if (!is_string($customerId)) {
            // Unknown identifier. Set null
            $event->getSession()->setCustomerId(null);
            return $event;
        }

        if (24 === strlen($customerId)) {
            // Mongo string id
            return $this->setCustomerIdFromMongoString($event, $customerId);
        }

        if (15 === strlen($customerId)) {
            // Omeda encrypted customer id
            return $this->setCustomerIdFromOmedaId($event, $customerId, $accountKey, $groupKey);
        }

        // Unknown or invalid. Set null
        $event->getSession()->setCustomerId(null);
        return $event;
    }

    /**
     * Sets the customer id from a Mongo id string
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $customerId
     * @return Cygnus\OlyticsBundle\Model\Event\EventInterface
     */
    protected function setCustomerIdFromMongoString(EventInterface $event, $customerId)
    {
        try {
            $mongoId = new \MongoId($customerId);
            $event->getSession()->setCustomerId($mongoId);
        } catch (\Exception $e) {
            $event->getSession()->setCustomerId(null);
        }
        return $event;
    }

    /**
     * Sets the customer id from an Omeda encrypted id
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $customerId
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return Cygnus\OlyticsBundle\Model\Event\EventInterface
     */
    protected function setCustomerIdFromOmedaId(EventInterface $event, $customerId, $accountKey, $groupKey)
    {
        $cacheKey = $this->getCacheKey($customerId, $accountKey, $groupKey);

        $cacheResult = $this->getCacheClient()->get($cacheKey);

        if (false === $cacheResult) {
            // Not found in cache. Retrieve from DB

            $builder = new Builder($this->getUserCollection());

            $cursor = $builder
                ->find()
                ->field('site')->equals($this->getWebsiteHost($groupKey))
                ->field('omeda_encrypted_id')->equals($customerId)
                ->select('_id', 'omeda_encrypted_id')
                ->getQuery()
                ->execute()
            ;

            $result = $cursor->getNext();

            $id = (is_array($result) && isset($result['_id'])) ? $result['_id'] : null;

            // Set to cache
            $this->getCacheClient()->setex($cacheKey, 60*60*24*7, serialize($id));
            $event->getSession()->setCustomerId($id);
            return $event;
        }

        $id = unserialize($cacheResult);
        $event->getSession()->setCustomerId($id);
        return $event;
    }

    /**
     * Gets the cache key for an Omeda encrypted id
     *
     * @param  string   $customerId
     * @param  string   $accountKey
     * @param  string   $groupKey
     * @return string
     */
    protected function getCacheKey($customerId, $accountKey, $groupKey)
    {
        return sprintf('Olytics:MongoUserIdHook:%s:%s:%s', $accountKey, $groupKey, $customerId);
    }

    /**
     * Sets the cache client
     *
     * @param  Snc\RedisBundle\Client\Phpredis\Client $cacheClient
     * @return self
     */
    public function setCacheClient($cacheClient)
    {
        $this->cacheClient = $cacheClient;
        return $this;
    }

    /**
     * Gets the cache client
     *
     * @return Snc\RedisBundle\Client\Phpredis\Client $cacheClient
     */
    public function getCacheClient()
    {
        return $this->cacheClient;
    }

    /**
     * Gets the user collection for retrieving Mongo user ids
     *
     * @return Doctrine\MongoDB\Collection
     */
    protected function getUserCollection()
    {
        return $this->getUserConnection()->selectCollection('merrick', 'users_v2');
    }

    /**
     * Sets The MongoDB connection that contains the user collection
     *
     * @param  Doctrine\MongoDB\Connection $connection
     * @return self
     */
    public function setUserConnection(Connection $connection)
    {
        $this->userConnection = $connection;
        return $this;
    }

    /**
     * Gets The MongoDB connection that contains the user collection
     *
     * @return Doctrine\MongoDB\Connection $connection
     */
    public function getUserConnection()
    {
        return $this->userConnection;
    }

    /**
     * Gets the website host by group key
     *
     * @todo   This should be configured at the Olytics application level
     * @param  string $groupKey
     * @return string|null
     */
    protected function getWebsiteHost($groupKey)
    {
        if (!isset($this->getWebsiteHosts()[$groupKey])) {
            return null;
        }
        return $this->getWebsiteHosts()[$groupKey];
    }

    /**
     * Returns the website hosts by group key
     *
     * @todo   This should be configured at the Olytics application level
     * @return array
     */
    protected function getWebsiteHosts()
    {
        return [
            'cavc'  => 'www.aviationpros.com',
            'll'    => 'www.locksmithledger.com',
            'ofcr'  => 'www.officer.com',
            'vspc'  => 'www.vehicleservicepros.com',
            'sdce'  => 'www.sdcexec.com',
            'fl'    => 'www.foodlogistics.com',
            'mass'  => 'www.masstransitmag.com',
            'gip'   => 'www.greenindustrypros.com',
            'mprc'  => 'www.myprintresource.com',
            'fcp'   => 'www.forconstructionpros.com',
            'ooh'   => 'www.oemoffhighway.com',
            'frpc'  => 'www.forresidentialpros.com',
            'csn'   => 'www.cpapracticeadvisor.com',
            'vmw'   => 'www.vendingmarketwatch.com',
            'emsr'  => 'www.emsworld.com',
            'fhc'   => 'www.firehouse.com',
            'siw'   => 'www.securityinfowatch.com',
        ];
    }

    /**
     * Determines if this hook supports the provided event, account, and group
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return bool
     */
    public function supports(EventInterface $event, $accountKey, $groupKey)
    {
        if (in_array($accountKey, ['cygnus', 'acbm']) && null !== $this->getWebsiteHost($groupKey)) {
            return true;
        }
        return false;
    }
}
