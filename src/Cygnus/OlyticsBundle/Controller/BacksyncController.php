<?php
namespace Cygnus\OlyticsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\MongoDB\Query\Builder;

class BacksyncController extends Controller
{
    const MAX_SOURCE_RECORDS = 500000;

    public $olyticsConns = [
        'db9'   => ['fhc'],
        'aws'   => ['ofcr', 'autm', 'cavc', 'll', 'vspc', 'sdce', 'fl', 'mass', 'gip', 'mprc', 'fcp', 'ooh', 'frpc', 'csn', 'emsr'],
    ];

    public $groups = ['cavc', 'll', 'ofcr', 'vspc', 'sdce', 'fl', 'mass', 'gip', 'mprc', 'fcp', 'ooh', 'frpc', 'csn', 'autm', 'emsr', 'fhc'];

    public function indexAction()
    {
        ini_set('memory_limit', '1024M');

        while (@ob_end_flush());
        ob_implicit_flush(true);
        echo str_repeat(' ', 4096);
        echo '<pre>';

        $query = $this->get('request')->query;

        $groups = $query->get('groups');
        if (empty($groups) || 'all' === $groups) {
            $groups = $this->groups;
        } else {
            $groups = explode(',',$groups);
        }

        $recordLimit = ($query->has('limit')) ? (int) $query->get('limit') : null;

        foreach ($groups as $group) {
            if (!in_array($group, $this->groups)) {
                echo sprintf('Group "%s" not found as a valid group. Skipping.'."\r\n\r\n", $group);
                continue;
            }

            $start = microtime(true);
            echo "Starting aggregation for group '{$group}'\r\n";

            $lastInsert = $this->getFeedLastInsert($group);

            echo sprintf("Requesting %s ad impressions since %s from %s::%s on %s\r\n", number_format($this->getSourceRecordLimit($recordLimit)), date('Y-m-d H:i:s', $lastInsert->sec), $this->getOlyticsDatabase($group), $this->getOlyticsColl($lastInsert), $this->getOlyticsConnName($group));

            // Get the ad events cursor
            $adEventCursor = $this->getAdEventsByDate($group, $lastInsert, $recordLimit);
            $cusorCount = $adEventCursor->count(true);

            echo sprintf("Cursor generation complete. Found %s ad events.\r\n", number_format($cusorCount));

            $page = $cusorCount / 100;

            echo sprintf("Starting aggregation of ad impressions. Each '.' represents ~%s records.\r\nProcessing\r\n", round($page, 2));
            echo str_repeat('-', 100) . "\r\n";

            $processed = 0;
            $percentage = 0;
            $periods = 0;
            $skipped = 0;
            $upserted = 0;

            // $builder = $this->createFeedQueryBuilder($group)
            //     ->update()
            //     ->upsert(true)
            // ;

            $collection = $this->getFeedConnection()->selectCollection($this->getFeedDb($group), $this->getFeedCollection($group))->getMongoCollection();
            foreach ($adEventCursor as $adEvent) {

                set_time_limit(10);

                if (!isset($adEvent['relatedEntities'])) {
                    $skipped++;
                    continue;
                }

                $data = $adEvent['relatedEntities'];

                if (!isset($data['adRequest']) || !isset($data['adRequest']['addAdUnit'])) {
                    $skipped++;
                    continue;
                }

                $upsert = $this->createUpsert($adEvent, $data['adRequest']);

                // $builder
                //     ->field('metadata')->equals($upsert['metadata'])
                //     ->setNewObj($upsert['doc'])
                // ;

                try {

                    $this->doFeedUpsert($collection, $upsert['metadata'], $upsert['doc']);
                    $upserted++;
                } catch (\Exception $e) {
                    echo sprintf('ERRORS FOUND :: %s', $e->getMessage());
                    break 2;
                }

                $processed++;
                $increment = 1 / $page;
                $floored = floor($increment);
                $percentage += $increment;

                if ($floored > 0) {
                    $periods += $floored;
                    echo str_repeat('.', $floored);
                } else if (floor($percentage) > 0) {
                    $periods += floor($percentage);
                    echo str_repeat('.', floor($percentage));
                    $percentage = 0;
                }

            }

            if ($periods < 100) {
                echo str_repeat('.', 100 - $periods);
            }

            $processingProfile = round(microtime(true) - $start, 3);
            $display = str_repeat('-', 100);

            $recordsPerSecond = round($upserted / $processingProfile, 2);

            echo "\r\n{$display}\r\nAggregation for {$group} complete. Processed: {$upserted}, Skipped: {$skipped}, Took: {$processingProfile} seconds ({$recordsPerSecond} rec/s). Memory: {$this->getMemoryUsage()}\r\n\r\n";
        }
        die();
    }

    public function doFeedUpsert($collection, $metadata, array $newObj)
    {
        try {
            $collection->update(['metadata' => $metadata], $newObj, ['upsert' => true]);
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

    protected function getMemoryUsage()
    {
        $memory = round(memory_get_usage() / (1024 * 1024)); // to get usage in Mo
        $memoryMax = round(memory_get_peak_usage() / (1024 * 1024)); // to get max usage in Mo

        return sprintf('(RAM : current=%uMo peak=%uMo)', $memory, $memoryMax);
    }

    public function createUpsert(array $adEvent, array $adRequest)
    {

        $metadata = [
            'start'     => new \MongoDate(strtotime(date('Y-m-01 00:00:00', $adEvent['createdAt']->sec))),
            'adUnitId'  => new \MongoInt32($adRequest['addAdUnit']),
            'keyValues' => $this->getKeyValuesFromRequest($adRequest),
        ];

        $upsertDoc = array(
            '$setOnInsert' => array(
                'metadata'  => $metadata,
        ),
            '$set'         => array(
                'lastInsert'   => $adEvent['createdAt'],
        ),
            '$inc'         => array(
                'impressions.total'  => 1,
                'impressions.ads.' . $adEvent['clientId'] => 1,
            ),
        );
        return ['doc' => $upsertDoc, 'metadata' => $metadata];
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
        $sponsorRemainders = array(
        '1'   => $valueIndex % 1,
        '2'   => $valueIndex % 2,
        '3'   => $valueIndex % 3,
        '4'   => $valueIndex % 4,
        );
        $sponsorSkip = array(
        0   => (bool) empty($sponsorRemainders[1]),
        1   => (bool) empty($sponsorRemainders[2]),
        2   => (bool) empty($sponsorRemainders[3]),
        3   => (bool) empty($sponsorRemainders[4]),
        );

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
        unset($keyValueData);
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

    public function getSourceRecordLimit($limit = null)
    {
        if (is_null($limit) || $limit > self::MAX_SOURCE_RECORDS || $limit == 0) {
            $limit = self::MAX_SOURCE_RECORDS;
        }
        return $limit;
    }

    public function getAdEventsByDate($group, \MongoDate $lastInsert = null, $recordLimit = null)
    {
        $maxRange = '+10 Days';
        $stop = strtotime('2014-10-27 08:00:00');
        $stop = (strtotime($maxRange, $lastInsert->sec) > $stop) ? $stop : strtotime($maxRange, $lastInsert->sec);

        return $this->createOlyticsQueryBuilder($group, $lastInsert)
            ->find()
            ->field('action')->equals('view')
            ->field('createdAt')->gte($lastInsert)->lt(new \MongoDate($stop))
            ->sort(['createdAt' => 1])
            ->limit($this->getSourceRecordLimit($recordLimit))
            ->getQuery()
            ->execute()
        ;
    }

    protected function getOlyticsDatabase($group)
    {
        if ($group === 'autm') {
            $group = 'vmw';
        }
        $account = (in_array($group, ['fcp', 'fl', 'ooh', 'sdce'])) ? 'acbm' : 'cygnus';
        return sprintf('oly_%s_%s', $account, $group);
    }

    protected function getOlyticsColl(\MongoDate $lastInsert)
    {
        $suffix = $this->getQuarterSuffix($lastInsert);
        return sprintf('event.ad.%s', $suffix);
    }

    protected function getQuarterSuffix(\MongoDate $lastInsert)
    {
        $date = new \DateTime();
        $date->setTimestamp($lastInsert->sec);

        $q4 = new \DateTime();
        $q4->setTimestamp(strtotime('2014-10-01 00:00:00'));

        $span = 60*60*4;
        $diff = $q4->getTimestamp() - $date->getTimestamp();

        if ($diff < $span) {
            $date = $q4;
        }

        return sprintf('%s_Q%s', $date->format('Y'), ceil($date->format('n') / 3));
    }

    protected function getOlyticsConnName($group)
    {
        $conType = null;

        foreach ($this->olyticsConns as $type => $groups) {
            if (in_array($group, $groups)) {
                $conType = $type;
                break;
            }
        }

        if (null === $conType) {
            throw new \InvalidArgumentException('Unable to determine the Olytics connection type.');
        }

        $conType = ($conType === 'aws') ? 'olytics' : $conType;
        return sprintf('doctrine_mongodb.odm.%s_connection', $conType);
    }

    protected function getOlyticsConnection($group)
    {
        return $this->get($this->getOlyticsConnName($group));
    }


    protected function createOlyticsQueryBuilder($group, \MongoDate $lastInsert)
    {
        $db = $this->getOlyticsDatabase($group);
        $coll = $this->getOlyticsColl($lastInsert);
        $collection = $this->getOlyticsConnection($group)->selectCollection($db, $coll);
        return new Builder($collection);
    }

    protected function createFeedQueryBuilder($group)
    {
        $collection = $this->getFeedConnection()->selectCollection($this->getFeedDb($group), $this->getFeedCollection($group));
        return new Builder($collection);
    }

    protected function getFeedDb($group)
    {
        return 'openx_feed';
    }

    protected function getFeedCollection($group)
    {
        return $group;
    }

    protected function getFeedConnection()
    {
        return $this->get($this->getFeedConnName());
    }

    protected function getFeedConnName()
    {
        return 'doctrine_mongodb.odm.olytics_connection';
    }

    protected function getFeedLastInsert($group)
    {
        $result = $this->createFeedQueryBuilder($group)
            ->find()
            ->select('lastInsert')
            ->exclude('_id')
            ->sort(['lastInsert' => -1])
            ->limit(1)
            ->getQuery()
            ->getSingleResult()
        ;
        if (is_array($result)) {
            return reset($result);
        }
        return null;
    }
}