<?php
namespace Cygnus\OlyticsBundle\Event;

use Cygnus\OlyticsBundle\Aggregation\Manager as AggregationManager;
use Cygnus\OlyticsBundle\EventHook\Manager as EventHookManager;
use Cygnus\OlyticsBundle\Index\IndexManager;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;
use Doctrine\MongoDB\Connection;

abstract class Persistor implements PersistorInterface
{
    protected $accounts;

    protected $connection;

    protected $indexManager;

    protected $am;

    protected $hm;

    protected $validator;

    public function __construct(Connection $connection, IndexManager $indexManager, AggregationManager $am, EventHookManager $hm, ValidatorInterface $validator, array $accounts)
    {
        $this->connection = $connection;
        $this->indexManager = $indexManager;
        $this->accounts = $accounts;
        $this->am = $am;
        $this->hm = $hm;
        $this->validator = $validator;
    }

    public function getEventValidator()
    {
        return $this->validator;
    }

    public function getAggregationManager()
    {
        return $this->am;
    }

    public function getEventHookManager()
    {
        return $this->hm;
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
