<?php
namespace Cygnus\OlyticsBundle\Event;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;
use Doctrine\MongoDB\Connection;

abstract class Persistor implements PersistorInterface
{
    protected $connection;

    public function __construct(Connection $connection) {
        $this->connection = $connection;
    }

    public function getConnection()
    {
        return $this->connection;
    }
}
