<?php

namespace Cygnus\OlyticsBundle\Aggregation\Brevity;

use Doctrine\Common\Inflector\Inflector;
use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class InfluencerAggregation extends AbstractAggregation
{
    /**
     * Gets the index mapping for this aggregation
     *
     * @return array
     */
    public function getIndexes()
    {
        return [
            ['keys' => ['issueId' => 1, 'customerId' => 1, 'source' => 1, 'medium' => 1], 'options' => ['unique' => true]],
        ];
    }

    /**
     * Executes the aggregation
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  string                                           $accountKey
     * @param  string                                           $groupKey
     * @return self
     */
    protected function doExecute(EventInterface $event, $accountKey, $groupKey, $appKey)
    {
        $dbName = $this->getDbName($accountKey, $groupKey);
        $collName = 'Influencers';

        // $this->createIndexes($dbName, $collName);

        $campaign = $event->getSession()->getCampaign();

        $upsertObj = [
            '$setOnInsert'  => [
                'issueId'           => $campaign['content'],
                'customerId'        => $campaign['influencer'],
            ],
            '$addToSet'     => [
                'visitors'  => new \MongoBinData($event->getSession()->getVisitorId(), \MongoBinData::UUID)
            ],
        ];

        if (isset($campaign['source'])) {
            $upsertObj['$setOnInsert']['source'] = $campaign['source'];
        }

        if (isset($campaign['medium'])) {
            $upsertObj['$setOnInsert']['medium'] = $campaign['medium'];
        }

        $influencedCustomerId = $event->getSession()->getCustomerId();
        if (!empty($influencedCustomerId)) {
            $upsertObj['$addToSet']['customers'] = $influencedCustomerId;
        }

        $builder = $this->createQueryBuilder($dbName, $collName)
            ->update()
            ->upsert(true)
            ->setNewObj($upsertObj)
        ;

        foreach (['issueId', 'customerId', 'source', 'medium'] as $field) {
            if (isset($upsertObj['$setOnInsert'][$field])) {
                $builder->field($field)->equals($upsertObj['$setOnInsert'][$field]);
            }
        }
        $builder->getQuery()->execute();
        return $this;
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

        if (!isset($campaign['influencer'])) {
            return false;
        }
        return true;
    }
}
