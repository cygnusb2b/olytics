<?php
namespace Cygnus\OlyticsBundle\Event;

use Cygnus\OlyticsBundle\Index\IndexManager;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;
use Doctrine\MongoDB\Connection;

abstract class Persistor implements PersistorInterface
{
    protected $accounts;

    protected $connection;

    protected $indexManager;

    public function __construct(Connection $connection, IndexManager $indexManager, array $accounts)
    {
        $this->connection = $connection;
        $this->indexManager = $indexManager;
        $this->accounts = $accounts;
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
