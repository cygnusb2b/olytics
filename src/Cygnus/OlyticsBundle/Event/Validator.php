<?php
namespace Cygnus\OlyticsBundle\Event;

use Cygnus\OlyticsBundle\Model\Event\EventInterface;
use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Exception\Model\InvalidModelException;
use Cygnus\OlyticsBundle\Exception\Model\InvalidEventException;
use Cygnus\OlyticsBundle\Exception\Model\InvalidEntityException;

class Validator implements ValidatorInterface
{
    /**
     * Validates an EventInterface instance to ensure it is acceptable for database persistence
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @return bool
     * @throws Cygnus\OlyticsBundle\Exception\Model\InvalidEventException
     */
    public function validate(EventInterface $event)
    {
        $entity = $event->getEntity();
        if (!$entity instanceof Entity) {
            throw InvalidEventException::invalidEntity($event, $entity, 'Cygnus\OlyticsBundle\Model\Metadata\Entity');
        }

        $action = $event->getAction();
        if (empty($action)) {
            throw InvalidEventException::missingAction($event, $action);
        }
        return $this->validateEntity($event);
    }

    /**
     * Validates the Entity of an EventInterface instance to ensure it is acceptable for database persistence
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @return bool
     * @throws Cygnus\OlyticsBundle\Exception\Model\InvalidEntityException
     */
    protected function validateEntity(EventInterface $event)
    {
        $entity     = $event->getEntity();
        $type       = $entity->getType();
        $clientId   = $entity->getClientId();

        if (empty($type)) {
            throw InvalidEntityException::missingType($event, $type);
        }

        if (empty($clientId)) {
            throw InvalidEntityException::missingClientId($event, $clientId);
        }
        return true;
    }

    /**
     * Notifies New Relic of an Exception
     *
     * @static
     * @param  \Exception   $e
     * @param  string       $accountKey
     * @param  string       $groupKey
     * @return void
     */
    public static function notifyError(\Exception $e, $accountKey, $groupKey)
    {
        if (extension_loaded('newrelic')) {
            newrelic_add_custom_parameter('accountKey', $accountKey);
            newrelic_add_custom_parameter('groupKey', $groupKey);

            if ($e instanceof InvalidModelException) {
                newrelic_add_custom_parameter('eventObject', serialize($e->getEvent()));
            }

            newrelic_notice_error($e->getMessage(), $e);
        }
    }
}