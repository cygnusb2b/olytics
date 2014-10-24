<?php

namespace Cygnus\OlyticsBundle\Aggregation;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;
use Cygnus\OlyticsBundle\Index\IndexManager;
use Doctrine\MongoDB\Connection;
use Doctrine\MongoDB\Query\Builder;

abstract class AbstractAggregation implements AggregationInterface
{
    /**
     * A list of acount + group keys that are enabled to use this aggregation
     * An empty array indicates that it is enabled for all
     *
     * @var array
     */
    protected $enabled = [];

    /**
     * The index manager to ensure indexes are created on aggregated collections
     *
     * @var Cygnus\OlyticsBundle\Index\IndexManager
     */
    protected $indexManager;

    /**
     * The MongoDB connection to write aggregated data to
     *
     * @var Doctrine\MongoDB\Connection
     */
    protected $connection;

    /**
     * Constructor.
     *
     * @param  Cygnus\OlyticsBundle\Index\IndexManager  $im
     * @param  Doctrine\MongoDB\Connection              $connection
     * @return void
     */
    public function __construct(IndexManager $im, Connection $connection)
    {
        $this->connection = $connection;
        $this->indexManager = $im;
    }

    /**
     * Gets the index mapping for this aggregation
     *
     * @abstract
     * @return array
     */
    abstract public function getIndexes();

    /**
     * Executes the aggregation
     *
     * @abstract
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return self
     */
    public function execute(EventInterface $event, $accountKey, $groupKey)
    {
        if (!$this->supports($event, $accountKey, $groupKey)) {
            return $this;
        }
        return $this->doExecute($event, $accountKey, $groupKey);
    }

    /**
     * Performs the the aggregation: contains the insertion/upsertion logic
     *
     * @abstract
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return self
     */
    abstract protected function doExecute(EventInterface $event, $accountKey, $groupKey);
    /**
     * Determines if this aggregation supports the provided event, account, and group
     *
     * @abstract
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return bool
     */
    abstract public function supports(EventInterface $event, $accountKey, $groupKey);

    /**
     * Gets the index manager
     *
     * @return Cygnus\OlyticsBundle\Index\IndexManager $im
     */
    public function getIndexManager()
    {
        return $this->indexManager;
    }

    /**
     * Creates the indexes for this aggregation
     *
     * @param  string   $dbName
     * @param  string   $collectionName
     * @return self
     */
    protected function createIndexes($dbName, $collectionName)
    {
        $indexes = $this->getIndexManager()->indexFactoryMulti($this->getIndexes());
        $this->getIndexManager()->createIndexes($indexes, $dbName, $collectionName);
        return $this;
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
     * Determines if an account and group is enabled to use this aggregation
     *
     * @param  string $accountKey
     * @param  string $groupKey
     * @return bool
     */
    public function isEnabled($accountKey, $groupKey)
    {
        if (empty($this->enabled)) {
            return true;
        }
        $key = sprintf('%s.%s', $accountKey, $groupKey);
        return isset($this->enabled[$key]);
    }

    /**
     * Adds an account+group to the enabled list
     *
     * @param  string $accountKey
     * @param  string $groupKey
     * @return self
     */
    public function enable($accountKey, $groupKey)
    {
        $key = sprintf('%s.%s', $accountKey, $groupKey);
        $this->enabled[$key] = true;
    }
}