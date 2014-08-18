<?php
namespace Cygnus\OlyticsBundle\Index;

use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Index;

class IndexManager
{

    protected $indexes = [];

    protected $cacheClient;

    protected $connection;

    public function __construct(Connection $connection, $cacheClient)
    {
        $this->connection = $connection;
        $this->cacheClient = $cacheClient;

        $this->setSessionIndexes();
        $this->setEventIndexes();
        $this->setEntityIndexes();
    }

    public function createIndexes($type, $dbName, $collName)
    {
        if (!$this->indexesExist($dbName, $collName)) {
            $this->doCreateIndexes($type, $dbName, $collName);
        }
    }

    protected function doCreateIndexes($type, $dbName, $collName)
    {
        $collection = $this->connection->selectCollection($dbName, $collName);

        foreach ($this->getIndexesFor($type) as $index) {
            $options = ($index->unique === true) ? ['unique' => true, 'background' => true] : ['background' => true];
            $collection->ensureIndex($index->keys, $options);
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

    public function getIndexes()
    {
        return $this->indexes;
    }

    public function getIndexesFor($type)
    {
        if (isset($this->indexes[$type])) {
            return $this->indexes[$type];
        }
        return [];
    }

    public function setSessionIndexes()
    {
        $this->addIndex('session', ['sessionId' => 1], true);
        $this->addIndex('session', ['visitorId' => 1]);
        $this->addIndex('session', ['customerId' => 1]);
    }

    public function setEventIndexes()
    {
        $this->addIndex('event', ['sessionId' => 1]);
        $this->addIndex('event', ['clientId' => 1, 'action' => 1]);
        $this->addIndex('event', ['createdAt' => 1]);
    }

    public function setEntityIndexes()
    {
        $this->addIndex('entity', ['clientId' => 1], true);
    }

    public function addIndex($collType, array $keys, $unique = false)
    {
        $collType = strtolower($collType);

        $data = ['keys' => $keys, 'unique' => $unique];
        $index = new Index($data);

        $this->indexes[$collType][] = $index;
    }

}

