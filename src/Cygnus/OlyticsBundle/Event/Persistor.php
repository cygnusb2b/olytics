<?php
namespace Cygnus\OlyticsBundle\Event;

use Cygnus\OlyticsBundle\Aggregation\Manager as AggregationManager;
use Cygnus\OlyticsBundle\Index\IndexManager;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;
use Doctrine\MongoDB\Connection;

abstract class Persistor implements PersistorInterface
{
    protected $accounts;

    protected $connection;

    protected $indexManager;

    protected $am;

    public function __construct(Connection $connection, IndexManager $indexManager, AggregationManager $am, array $accounts)
    {
        $this->connection = $connection;
        $this->indexManager = $indexManager;
        $this->accounts = $accounts;
        $this->am = $am;
    }

    public function getAggregationManager()
    {
        return $this->am;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function getIndexManager()
    {
        return $this->indexManager;
    }
}
