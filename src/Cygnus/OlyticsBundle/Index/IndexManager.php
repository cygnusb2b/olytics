<?php
namespace Cygnus\OlyticsBundle\Index;

use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Index;

class IndexManager
{
    protected $cacheClient;

    protected $connection;

    public function __construct(Connection $connection, $cacheClient)
    {
        $this->connection = $connection;
        $this->cacheClient = $cacheClient;
    }

    public function createIndexes(array $indexes, $dbName, $collName)
    {
        if (!$this->indexesExist($dbName, $collName)) {
            $this->doCreateIndexes($indexes, $dbName, $collName);
        }
    }

    protected function notifyNewRelic($message, $dbName, $collName, $response)
    {
        if (extension_loaded('newrelic')) {
            newrelic_add_custom_parameter('dbName', $dbName);
            newrelic_add_custom_parameter('collName', $collName);
            newrelic_add_custom_parameter('response', serialize($response));
            newrelic_notice_error($message);
        }
    }

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
                return false;
            }
        }
        $cacheKey = $this->getCacheKey($dbName, $collName);
        $this->cacheClient->set($cacheKey, true);
    }

    public function indexesExist($dbName, $collName)
    {
        $cacheKey = $this->getCacheKey($dbName, $collName);
        if ($indexes = $this->cacheClient->get($cacheKey)) {
            return true;
        }
        return false;
    }

    public function getCacheKey($dbName, $collName)
    {
        return sprintf('Olytics:IndexManager:%s:%s', $dbName, $collName);
    }

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

