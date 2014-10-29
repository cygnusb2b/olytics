<?php
namespace Cygnus\OlyticsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\MongoDB\Query\Builder;

class MergeController extends Controller
{
    const MAX_SOURCE_RECORDS = 200000;

    const FINAL_IMPORT_DATE = '2014-10-27 08:00:00';

    const BATCH_SIZE_DEFAULT = 1000;
    const BATCH_SIZE_MIN = 100;
    const BATCH_SIZE_MAX = 5000;

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

        $recordLimit = ($query->has('limit')) ? (Integer) $query->get('limit') : null;

        // $batchSize = ($query->has('batch')) ? (Integer) $query->get('batch') : self::BATCH_SIZE_DEFAULT;
        // $batchSize = ($batchSize > self::BATCH_SIZE_MAX) ? self::BATCH_SIZE_MAX : $batchSize;
        // $batchSize = ($batchSize < self::BATCH_SIZE_MIN) ? self::BATCH_SIZE_MIN : $batchSize;

        foreach ($groups as $group) {
            if (!in_array($group, $this->groups)) {
                echo sprintf('Group "%s" not found as a valid group. Skipping.'."\r\n\r\n", $group);
                continue;
            }

            $start = microtime(true);
            echo "Starting merge for group '{$group}'\r\n";

            $lastMerged = $this->getFeedLastMerged($group);

            $ts = ($lastMerged === null) ? 0 : $lastMerged->sec;
            echo sprintf("Requesting %s OpenX feeds since %s from %s::%s on %s\r\n", number_format($this->getSourceRecordLimit($recordLimit)), date('Y-m-d H:i:s', $ts), $this->getOpenXFeedDb($group), $this->getOpenXFeedCollection($group), $this->getOpenXFeedConnName());

            $cursor = $this->getOpenXFeedsByDate($group, $lastMerged, $recordLimit);
            $cursorCount = $cursor->count(true);

            echo sprintf("Cursor generation complete. Found %s OpenX feeds.\r\n", number_format($cursorCount));


            $page = $cursorCount / 100;

            echo sprintf("Starting merge of OpenX feeds. Each '.' represents ~%s records.\r\nProcessing\r\n", round($page, 2));
            echo str_repeat('-', 100) . "\r\n";

            $processed = 0;
            $percentage = 0;
            $periods = 0;
            $skipped = 0;
            $upserted = 0;

            $collection = $this->getOlyticsAggConnection()->selectCollection($this->getOlyticsAggDb($group), $this->getOlyticsAggCollection($group))->getMongoCollection();

            foreach ($cursor as $feed) {

                set_time_limit(10);

                $metadata = [
                    'start'     => $feed['metadata']['start'],
                    'adUnitId'  => $feed['metadata']['adUnitId'],
                    'keyValues' => $feed['metadata']['keyValues'],
                ];

                $upsertDoc = array(
                    '$setOnInsert'  => [
                        '_id'           => $feed['_id'],
                        'metadata'      => $metadata,
                        'lastInsert'    => $feed['lastInsert'],
                    ],
                    '$set'          => [
                        'lastMerged'    => $feed['lastInsert'],
                    ],
                    '$inc'          => [
                        'impressions.total'  => $feed['impressions']['total'],
                    ],
                );

                foreach ($feed['impressions']['ads'] as $clientId => $impressions) {
                    $upsertDoc['$inc']['impressions.'.$clientId] = $impressions;
                }

                try {
                    $collection->update(['metadata' => $metadata], $upsertDoc, ['upsert' => true]);
                    $upserted++;
                } catch (\Exception $e) {
                    echo sprintf('ERRORS FOUND :: %s', $e->getMessage());
                    die();
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
            echo "\r\n{$display}\r\nAggregation for {$group} complete. Processed: {$upserted}, Took: {$processingProfile} seconds ({$recordsPerSecond} rec/s). Memory: {$this->getMemoryUsage()}\r\n\r\n";
        }
        die();
    }

    protected function getMemoryUsage()
    {
        $memory = round(memory_get_usage() / (1024 * 1024)); // to get usage in Mo
        $memoryMax = round(memory_get_peak_usage() / (1024 * 1024)); // to get max usage in Mo

        return sprintf('(RAM : current=%uMo peak=%uMo)', $memory, $memoryMax);
    }

    public function doBatchInsert(array $toInsert, $collection)
    {

    }

    public function getOpenXFeedsByDate($group, \MongoDate $lastMerged = null, $recordLimit = null)
    {
        $ts = ($lastMerged === null) ? 0 : $lastMerged->sec;

        $builder = $this->createOpenXFeedQueryBuilder($group, $lastMerged)
            ->find()
            ->sort(['lastInsert' => 1])
        ;

        if ($ts === 0) {
            $builder->limit(1);
        } else {

            $requestedStop = strtotime('+3 Months', $lastMerged->sec);
            $mustStop = strtotime(self::FINAL_IMPORT_DATE);

            $stop = ($requestedStop > $mustStop) ? $mustStop : $requestedStop;

            $builder
                ->field('lastInsert')->gt($lastMerged)->lt(new \MongoDate($stop))
                ->limit($this->getSourceRecordLimit($recordLimit))
            ;
        }

        return $builder
            ->getQuery()
            ->execute()
        ;
    }

    public function getSourceRecordLimit($limit = null)
    {
        if (is_null($limit) || $limit > self::MAX_SOURCE_RECORDS || $limit == 0) {
            $limit = self::MAX_SOURCE_RECORDS;
        }
        return $limit;
    }


    protected function createOlyticsAggQueryBuilder($group)
    {
        $collection = $this->getOlyticsAggConnection()->selectCollection($this->getOlyticsAggDb($group), $this->getOlyticsAggCollection($group));
        return new Builder($collection);
    }

    protected function getOlyticsAggDb($group)
    {
        if ($group === 'autm') {
            $group = 'vmw';
        }
        $account = (in_array($group, ['fcp', 'fl', 'ooh', 'sdce'])) ? 'acbm' : 'cygnus';
        return sprintf('oly_%s_%s_aggregation', $account, $group);
    }

    protected function getOlyticsAggCollection($group)
    {
        return 'openx_feed';
    }

    protected function getOlyticsAggConnection()
    {
        return $this->get($this->getOlyticsAggConnName());
    }

    protected function getOlyticsAggConnName()
    {
        return 'doctrine_mongodb.odm.olytics_connection';
    }

    protected function createOpenXFeedQueryBuilder($group)
    {
        $collection = $this->getOpenXFeedConnection()->selectCollection($this->getOpenXFeedDb($group), $this->getOpenXFeedCollection($group));
        return new Builder($collection);
    }

    protected function getOpenXFeedDb($group)
    {
        return 'openx_feed';
    }

    protected function getOpenXFeedCollection($group)
    {
        return $group;
    }

    protected function getOpenXFeedConnection()
    {
        return $this->get($this->getOpenXFeedConnName());
    }

    protected function getOpenXFeedConnName()
    {
        return 'doctrine_mongodb.odm.olytics_connection';
    }

    protected function getFeedLastMerged($group)
    {
        $result = $this->createOlyticsAggQueryBuilder($group)
            ->find()
            ->select('lastMerged')
            ->exclude('_id')
            ->field('lastMerged')->lt(new \MongoDate(strtotime(self::FINAL_IMPORT_DATE)))
            ->sort(['lastMerged' => -1])
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
