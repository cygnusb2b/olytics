<?php
namespace Cygnus\OlyticsBundle\Event;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;
use Doctrine\MongoDB\Connection;

abstract class Persistor implements PersistorInterface
{
    protected $accounts;

    protected $connection;

    public function __construct(Connection $connection, array $accounts) {
        $this->connection = $connection;
        $this->accounts = $accounts;
    }

    public function getConnection()
    {
        return $this->connection;
    }
}
