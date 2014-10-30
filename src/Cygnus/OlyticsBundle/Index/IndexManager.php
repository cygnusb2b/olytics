<?php
namespace Cygnus\OlyticsBundle\Index;

use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Index;

class IndexManager
{
    /**
     * The cache client service for caching the existence of indexes
     *
     * @var object
     */
    protected $cacheClient;

    /**
     * The MongoDB connection to write the indexes to
     *
     * @var Doctrine\MongoDB\Connection
     */
    protected $connection;

    /**
     * Constructor.
     *
     * @param  Doctrine\MongoDB\Connection  $connection
     * @param  object                       $cacheClient
     * @return void
     */
    public function __construct(Connection $connection, $cacheClient)
    {
        $this->connection = $connection;
        $this->cacheClient = $cacheClient;
    }

    /**
     * Creates the indexes on the DB and Collection
     * Will check cache to see if the indexes already exist before executing
     *
     * @param  array<Doctrine\ODM\MongoDB\Mapping\Annotations\Index>    $indexes
     * @param  string                                                   $dbName
     * @param  string                                                   $collName
     * @return self
     */
    public function createIndexes(array $indexes, $dbName, $collName)
    {
        if (!$this->indexesExist($dbName, $collName)) {
            $this->doCreateIndexes($indexes, $dbName, $collName);
        }
        return $this;
    }

    /**
     * Notifies New Relic of an error
     *
     * @param  string   $message
     * @param  string   $dbName
     * @param  string   $collName
     * @param  mixed    $response
     * @return void
     */
    protected function notifyNewRelic($message, $dbName, $collName, $response)
    {
        if (extension_loaded('newrelic')) {
            newrelic_add_custom_parameter('dbName', $dbName);
            newrelic_add_custom_parameter('collName', $collName);
            newrelic_add_custom_parameter('response', serialize($response));
            newrelic_notice_error($message);
        }
    }

    /**
     * Creates the indexes on a MongoDB collection
     *
     * @param  array<Doctrine\ODM\MongoDB\Mapping\Annotations\Index>    $indexes
     * @param  string                                                   $dbName
     * @param  string                                                   $collName
     * @return self
     */
    protected function doCreateIndexes(array $indexes, $dbName, $collName)
    {
        $collection = $this->connection->selectCollection($dbName, $collName);
        foreach ($indexes as $index) {

            if (!$index instanceof Index) {
                throw new \InvalidArgumentException('Each index must be an instance of Doctrine\ODM\MongoDB\Mapping\Annotations\Index');
            }

            $options = ['background' => true, 'w' => 1];
            if ($index->unique) {
                $options['unique'] = true;
            }

            if (0 < $expire = $index->expireAfterSeconds) {
                $options['expireAfterSeconds'] = $expire;
            }

            $result = $collection->ensureIndex($index->keys, $options);
            if (!$result || $result['ok'] != 1) {
                $this->notifyNewRelic('Unable to create index', $dbName, $collName, $result);
                return $this;
            }
        }
        $cacheKey = $this->getCacheKey($dbName, $collName);
        $this->cacheClient->set($cacheKey, true);
        return $this;
    }

    /**
     * Determines if an index exists
     *
     * @param  string   $dbName
     * @param  string   $collName
     * @return bool
     */
    public function indexesExist($dbName, $collName)
    {
        $cacheKey = $this->getCacheKey($dbName, $collName);
        if ($indexes = $this->cacheClient->get($cacheKey)) {
            return true;
        }
        return false;
    }

    /**
     * Gets the cache key of a DB and Collection
     *
     * @param  string   $dbName
     * @param  string   $collName
     * @return bool
     */
    public function getCacheKey($dbName, $collName)
    {
        $conName = str_replace(':', 'p', (String) $this->connection);
        return sprintf('Olytics:IndexManager:%s:%s', $dbName, $collName);
    }

    /**
     * Factory that creates Index objects from an array of index definitions
     *
     * @param  array   $indexes
     * @return array<Doctrine\ODM\MongoDB\Mapping\Annotations\Index>
     * @throws \InvalidArgumentException If index mapping is invalid
     */
    public function indexFactoryMulti(array $indexes)
    {
        $objects = [];
        foreach ($indexes as $index) {
            if (!is_array($index) || !isset($index['keys']) || !is_array($index['keys'])) {
                throw new \InvalidArgumentException('Each index must contain an array of keys.');
            }
            if (!isset($index['options'])) {
                $index['options'] = [];
            }
            if (!is_array($index['options'])) {
                throw new \InvalidArgumentException('Options must be passed as an array.');
            }

            $objects[] = $this->indexFactory($index['keys'], $index['options']);
        }
        return $objects;
    }

    /**
     * Factory that creates a single Index from an index mapping
     *
     * @param  array    $keys
     * @param  array    $options
     * @return Doctrine\ODM\MongoDB\Mapping\Annotations\Index
     */
    public function indexFactory(array $keys, array $options)
    {
        $data = [];
        $data['keys'] = $keys;

        foreach ($options as $key => $option) {
            $data[$key] = $option;
        }
        return new Index($data);
    }

}

