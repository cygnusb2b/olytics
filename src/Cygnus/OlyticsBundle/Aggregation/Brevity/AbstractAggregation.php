<?php

namespace Cygnus\OlyticsBundle\Aggregation\Brevity;

use Cygnus\OlyticsBundle\Aggregation\AbstractAggregation as BaseAbstractAggregation;
use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;

abstract class AbstractAggregation extends BaseAbstractAggregation
{
    public function getDbName($accountKey, $groupKey)
    {
        return sprintf('brevity_reports_%s_%s', $accountKey, $groupKey);
    }

    /**
     * Determines if this aggregation supports the provided event, account, and group
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return bool
     */
    public function supports(EventInterface $event, $accountKey, $groupKey, $appKey)
    {
        if (!$this->isEnabled($accountKey, $groupKey)) {
            return false;
        }

        if ('brevity' !== $appKey) {
            return false;
        }

        $campaign = $event->getSession()->getCampaign();
        if (!isset($campaign['content'])) {
            return false;
        }

        $customerId = $event->getSession()->getCustomerId();
        return !empty($customerId);
    }
}
