<?php

namespace Cygnus\OlyticsBundle\Aggregation;

use Cygnus\OlyticsBundle\Model\Metadata\Entity;
use Cygnus\OlyticsBundle\Model\Event\EventInterface;

class OpenXImpressionAggregation extends AbstractAggregation
{
    /**
     * Gets the index mapping for this aggregation
     *
     * @return array
     */
    public function getIndexes()
    {
        return [
            ['keys' => ['metadata' => 1], 'options' => ['unique' => true]],
            ['keys' => ['lastInsert' => -1], 'options' => []],
            ['keys' => ['metadata.start' => 1], 'options' => []],
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
        $dbName = 'openx_feed';
        $collName = sprintf('%s_%s', $accountKey, $groupKey);

        $this->createIndexes($dbName, $collName);

        $relatedEntities = $event->getRelatedEntities();
        if (empty($relatedEntities)) {
            // No related entities found. DNP
            return $this;
        }

        foreach ($relatedEntities as $entity) {
            if (!$entity instanceof Entity) {
                // DNP
                continue;
            }
            if ('ad_request' !== $entity->getType()) {
                // Not an ad request. DNP
                continue;
            }
            if (!isset($entity->getKeyValues()['addAdUnit'])) {
                // Not valid. DNP
                continue;
            }

            $upsertObj = $this->createUpsertObject($event, $entity);

            $builder = $this->createQueryBuilder($dbName, $collName)
                ->update()
                ->upsert(true)
                ->field('metadata')->equals($upsertObj['$setOnInsert']['metadata'])
                ->setNewObj($upsertObj)
            ;

            try {
                $builder->getQuery()->execute();
            } catch (\MongoCursorException $e) {
                if ($e->getCode() != 17280) {
                    // Throw all but 'key too large to index'
                    throw $e;
                }
            } catch (\Exception $e) {
                // Throw all other Exceptions
                throw $e;
            }
        }
        return $this;
    }

    /**
     * Creates the upsert object for Mongo
     *
     * @param  Cygnus\OlyticsBundle\Model\Event\EventInterface  $event
     * @param  Cygnus\OlyticsBundle\Model\Metadata\Entity       $entity
     * @return array
     */
    protected function createUpsertObject(EventInterface $event, Entity $entity)
    {
        $metadata = [
            'start'     => new \MongoDate(strtotime($event->getCreatedAt()->format('Y-m-01 00:00:00'))),
            'adUnitId'  => new \MongoInt32($entity->getKeyValues()['addAdUnit']),
            'keyValues' => $this->getKeyValuesFromRequest($entity->getKeyValues()),
        ];

        $upsertDoc = [
            '$setOnInsert' => [
                'metadata'  => $metadata,
            ],
            '$set'         => [
                'lastInsert'   => new \MongoDate($event->getCreatedAt()->getTimestamp()),
            ],
            '$inc'         => [
                'impressions.total'  => 1,
                'impressions.ads.' . $event->getEntity()->getClientId() => 1,
            ],
        ];

        return $upsertDoc;
    }

    public function getKeyValuesFromRequest(array $keyValues)
    {
        $include = array(
            // 'company_id',
            // 'content_id',
            'content_type' => 'contentType',
            // 'env',
            'layout' => 'layout',
            // 'page',
            // 'pathname',
            // 'search_term',
            // 'section_id',
            'term_vocab_ids' => 'termVocabIds',
            'frequency_by_id' => 'freqGroupById',
        );
        $keyValueData = [];

        if (isset($keyValues['addVariable']) && is_array($keyValues['addVariable'])) {
            foreach ($keyValues['addVariable'] as $key => $value) {
                if (!isset($include[$key])) continue;

                $newKey = $include[$key];

                if (is_array($value)) {
                    foreach ($value as $v) {
                        if (!empty($v)) {
                            $keyValueData[$newKey][] = $v;
                        }
                    }
                } else {
                    if (!empty($value)) {

                        if ($key == 'frequency_by_id') {

                            $valueIndex = $value - 1;
                            $sponsorRemainders = [
                                '1'   => $valueIndex % 1,
                                '2'   => $valueIndex % 2,
                                '3'   => $valueIndex % 3,
                                '4'   => $valueIndex % 4,
                            ];
                            $sponsorSkip = [
                                0   => (bool) empty($sponsorRemainders[1]),
                                1   => (bool) empty($sponsorRemainders[2]),
                                2   => (bool) empty($sponsorRemainders[3]),
                                3   => (bool) empty($sponsorRemainders[4]),
                            ];

                            switch (true) {
                                case ($value == 1):
                                    $group = '1';
                                    break;
                                case ($value < 6):
                                    $group = '2 - 5';
                                    break;
                                case ($value < 10):
                                    $group = '6 - 9';
                                    break;
                                case ($value >= 10):
                                    $group = '10+';
                                    break;
                                default:
                                    $group = null;
                                    break;
                                }
                            $keyValueData[$newKey][] = $group;
                            $keyValueData['sponsorSkip'] = $sponsorSkip;
                        } else {
                            $keyValueData[$newKey][] = $value;
                        }
                    }
                }
            }
        }

        if (isset($keyValues['addContentTopic'])) {
            if (is_array($keyValues['addContentTopic'])) {
                foreach ($keyValues['addContentTopic'] as $value) {
                    $keyValueData['topicIds'][] = $value;
                }
            } else {
                $keyValueData['topicIds'][] = $keyValues['addContentTopic'];
            }
        }

        $keyValues = [];
        foreach ($keyValueData as $key => $values) {

            if (count($values) > 1 || $key == 'termVocabIds' || $key == 'topicIds') {
                // Multi-value

                $keyValues[$key] = array();
                foreach ($values as $value) {
                    // @TODO NEED TO INFORCE SOME SORT OF DATA SCHEMA FOR INTEGARS AND STRINGS!!

                    $keyValues[$key][] = $this->formatKeyValue($value);
                }
                // Do not store empty arrays
                if (empty($keyValues[$key])) unset($keyValues[$key]);

            } else {
                // Single value
                $value = $this->formatKeyValue($values[0]);
                $keyValues[$key] = $value;
            }
        }
        return $keyValues;
    }

    public function formatKeyValue($v)
    {
        if (is_numeric($v)) {
            if (preg_match('/^[+-]?(\d*\.\d+([eE]?[+-]?\d+)?|\d+[eE][+-]?\d+)$/', $v)) {
                // Float
                $v = (double) $v;
            } else {
                // Integer
                $v = (int) $v;
            }
        } elseif (is_bool($v)) {
            $v = (bool) $v;
        } elseif (is_string($v)) {
            // String values
            $value = strtolower($v);
            switch ($value) {
                case 'null':
                    $v = null;
                    break;
                case 'true':
                    $v = true;
                    break;
                case 'false':
                    $v = false;
                    break;
                case '':
                    $v = null;
                    break;
                default:
                    break;
            }
        }
        return $v;
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

        return 'ad' === $event->getEntity()->getType();
    }
}
