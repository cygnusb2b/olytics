<?php
namespace Cygnus\OlyticsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\MongoDB\Query\Builder;
use Cygnus\OlyticsBundle\Index\IndexManager;


class ConversionController extends Controller
{
    public $groups = ['cavc', 'siw', 'll', 'ofcr', 'vspc', 'sdce', 'fl', 'mass', 'gip', 'mprc', 'fcp', 'ooh', 'frpc', 'csn', 'vmw', 'emsr', 'fhc'];

    public $quarters = ['2014_Q3', '2014_Q4'];

    public $sites = [
        'cavc' => 'www.aviationpros.com',
        'll' => 'www.locksmithledger.com',
        'ofcr' => 'www.officer.com',
        'vspc' => 'www.vehicleservicepros.com',
        'sdce' => 'www.sdcexec.com',
        'fl' => 'www.foodlogistics.com',
        'mass' => 'www.masstransitmag.com',
        'gip' => 'www.greenindustrypros.com',
        'mprc' => 'www.myprintresource.com',
        'fcp' => 'www.forconstructionpros.com',
        'ooh' => 'www.oemoffhighway.com',
        'frpc' => 'www.forresidentialpros.com',
        'csn' => 'www.cpapracticeadvisor.com',
        'vmw' => 'www.vendingmarketwatch.com',
        'emsr' => 'www.emsworld.com',
        'fhc' => 'www.firehouse.com',
        'siw' => 'www.securityinfowatch.com',
    ];

    protected function init()
    {
        if (extension_loaded('newrelic')) {
            newrelic_background_job(true);
        }

        ini_set('memory_limit', '1024M');

        while (@ob_end_flush());
        ob_implicit_flush(true);
        echo str_repeat(' ', 4096);
        echo '<pre>';
    }

    public function createSessionArchiveAction()
    {
        die('done');
        $this->init();

        $groups = $this->getGroups();

        $conName = 'doctrine_mongodb.odm.olytics_connection';
        $connection = $this->get($conName);

        $cacheClient = $this->get('snc_redis.cache');
        $indexManager = new IndexManager($connection, $cacheClient);

        $sessionIndexes = $indexManager->indexFactoryMulti([
            ['keys' => ['month' => 1, 'contentId' => 1, 'userId' => 1, 'sessionId' => 1], 'options' => ['unique' => true]],
            ['keys' => ['lastAccessed' => 1], 'options' => []], // @todo Convert this to a TTL collection for 60 days
        ]);

        foreach ($groups as $group) {

            if (!in_array($group, $this->groups)) {
                echo sprintf('Group "%s" not found as a valid group. Skipping.'."\r\n\r\n", $group);
                continue;
            }

            $start = microtime(true);

            $account = (in_array($group, ['fcp', 'fl', 'ooh', 'sdce'])) ? 'acbm' : 'cygnus';

            $fromDb = sprintf('oly_%s_%s_events', $account, $group);
            $fromColl = 'content';

            $toDb = 'content_session_archive';
            $toColl = sprintf('%s_%s', $account, $group);

            echo "Creating/upserting session archive for group '{$group}'\r\n";

            $indexManager->createIndexes($sessionIndexes, $toDb, $toColl);

            $builder = new Builder($connection->selectCollection($toDb, $toColl));
            $result = $builder
                ->find()
                ->select('lastAccessed')
                ->exclude('_id')
                ->sort(['lastAccessed' => -1])
                ->limit(1)
                ->getQuery()
                ->getSingleResult()
            ;

            $ts = (null === $result) ? 0 : $result['lastAccessed']->sec;

            $limit = 200000;
            echo sprintf('Requesting %s records since %s from %s::%s on %s'."\r\n", number_format($limit), date('Y-m-d H:i:s', $ts), $fromDb, $fromColl, $conName);
            $builder = new Builder($connection->selectCollection($fromDb, $fromColl));
            $cursor = $builder
                ->find()
                ->field('createdAt')->gt(new \MongoDate($ts))
                ->field('action')->equals('view')
                ->sort(['createdAt' => 1])
                ->limit($limit)
                ->getQuery()
                ->execute()
            ;

            $count = $cursor->count(true);
            echo sprintf('Cursor generation complete. Found %s content events. Begin processing.'."\r\n", number_format($count));

            $page = $count / 100;

            echo sprintf('Updating events in %s::%s - Each \'.\' represents ~%s content events'."\r\n", $toDb, $toColl, $page);
            echo str_repeat('-', 100) . "\r\n";

            $collection = $connection->selectCollection($toDb, $toColl)->getMongoCollection();

            $processed = 0;
            $percentage = 0;
            $periods = 0;
            $skipped = 0;
            $upserted = 0;

            foreach ($cursor as $doc) {

                set_time_limit(10);

                $insert = [
                    'month'     => new \MongoDate(strtotime(date('Y-m-01 00:00:00', $doc['createdAt']->sec))),
                    'contentId' => (int) $doc['clientId'],
                    'sessionId' => $doc['session']['id'],
                ];

                if (isset($doc['userId'])) {
                    $insert['userId'] = $doc['userId'];
                }

                $newObj = [
                    '$setOnInsert'  => $insert,
                    '$set'          => [
                        'lastAccessed'  => $doc['createdAt'],
                    ],
                ];

                $criteria = [
                    'month'     => $insert['month'],
                    'contentId' => $insert['contentId'],
                    'sessionId' => $insert['sessionId'],
                    'userId'    => isset($insert['userId']) ? $insert['userId'] : ['$exists' => false],
                ];

                $processed++;
                $increment = 1 / $page;
                $floored = floor($increment);
                $percentage += $increment;

                try {
                    $collection->update($criteria, $newObj, ['upsert' => true]);
                    $upserted++;
                } catch (\Exception $e) {
                    echo sprintf('ERRORS FOUND :: %s', $e->getMessage());
                    die();
                }

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
            echo "\r\n";

            $time = round(microtime(true) - $start, 3);
            $recsPerSec = round($upserted / $time, 2);
            echo str_repeat('-', 100)."\r\n";
            echo sprintf('Upsert complete for %s. Took %ss for %s recs/sec %s'."\r\n\r\n", $group, $time, $recsPerSec, $this->getMemoryUsage());
        }
        die();
    }

    public function backfillSessionArchiveAction()
    {
        die('done');
        $this->init();

        $groups = $this->getGroups();

        $conName = 'doctrine_mongodb.odm.db9_connection';
        $connection = $this->get($conName);

        $sessionConName = 'doctrine_mongodb.odm.olytics_connection';
        $sessionCon = $this->get($sessionConName);

        foreach ($groups as $group) {
            if (!in_array($group, $this->groups)) {
                echo sprintf('Group "%s" not found as a valid group. Skipping.'."\r\n\r\n", $group);
                continue;
            }

            $start = microtime(true);

            $account = (in_array($group, ['fcp', 'fl', 'ooh', 'sdce'])) ? 'acbm' : 'cygnus';

            echo "Backfilling session archive data for group '{$group}'\r\n";

            $fromDb = 'content_traffic_archive';
            $fromColl = sprintf('%s_%s', $account, $group);

            $toDb = 'content_session_archive';
            $toColl = sprintf('%s_%s', $account, $group);

            $builder = new Builder($sessionCon->selectCollection($toDb, $toColl));
            $result = $builder
                ->find()
                ->select('month')
                ->exclude('_id')
                ->sort(['lastAccessed' => 1])
                ->limit(1)
                ->getQuery()
                ->getSingleResult()
            ;

            $ts = (null === $result) ? 0 : $result['month']->sec;

            echo sprintf('Requesting all records for month %s from %s::%s on %s'."\r\n", date('Y-m-d H:i:s', $ts), $fromDb, $fromColl, $conName);

            $builder = new Builder($connection->selectCollection($fromDb, $fromColl));
            $cursor = $builder
                ->find()
                ->field('metadata.month')->equals(new \MongoDate($ts))
                ->getQuery()
                ->execute()
            ;

            $count = $cursor->count(true);
            echo sprintf('Cursor generation complete. Found %s content archive records. Begin processing.'."\r\n", number_format($count));

            $page = $count / 100;

            echo sprintf('Updating session archive in %s::%s - Each \'.\' represents ~%s content archive records'."\r\n", $toDb, $toColl, $page);
            echo str_repeat('-', 100) . "\r\n";

            $processed = 0;
            $percentage = 0;
            $periods = 0;
            $skipped = 0;
            $upserted = 0;

            $sessionCollection = $sessionCon->selectCollection($toDb, $toColl)->getMongoCollection();

            foreach ($cursor as $doc) {

                if (!isset($doc['visits'])) {
                    $doc['visits'] = 1;
                }

                for ($i = 0; $i < $doc['visits']; $i++) {
                    set_time_limit(10);
                    $sessionId = sprintf('legacy_backfill_%s', $i);

                    $criteria = [
                        'month'     => $doc['metadata']['month'],
                        'contentId' => (int) $doc['metadata']['contentId'],
                        'sessionId' => $sessionId,
                    ];

                    $newObj = [
                        '$setOnInsert'  => $criteria,
                    ];

                    $newObj['$setOnInsert']['lastAccessed'] = $doc['lastAccessed'];

                    if (isset($doc['metadata']['userId'])) {
                        $criteria['userId'] = $doc['metadata']['userId'];
                        $newObj['$setOnInsert']['userId'] = $criteria['userId'];

                    } else {
                        $criteria['userId'] = ['$exists' => false];
                    }

                    try {
                        $sessionCollection->update($criteria, $newObj, ['upsert' => true]);
                    } catch (\Exception $e) {
                        echo sprintf('ERRORS FOUND :: %s', $e->getMessage());
                        die();
                    }


                }

                $upserted++;

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
            echo "\r\n";

            $time = round(microtime(true) - $start, 3);
            $recsPerSec = round($upserted / $time, 2);
            echo str_repeat('-', 100)."\r\n";
            echo sprintf('Upsert complete for %s. Took %ss for %s recs/sec %s'."\r\n\r\n", $group, $time, $recsPerSec, $this->getMemoryUsage());
        }

        die();


    }

    public function archiveAction()
    {
        $this->init();

        $groups = $this->getGroups();

        $conName = 'doctrine_mongodb.odm.olytics_connection';
        $connection = $this->get($conName);

        $sessionConName = 'doctrine_mongodb.odm.olytics_connection';
        $sessionCon = $this->get($sessionConName);

        foreach ($groups as $group) {

            if (!in_array($group, $this->groups)) {
                echo sprintf('Group "%s" not found as a valid group. Skipping.'."\r\n\r\n", $group);
                continue;
            }

            $start = microtime(true);

            $account = (in_array($group, ['fcp', 'fl', 'ooh', 'sdce'])) ? 'acbm' : 'cygnus';

            echo "Upserting to content archive for group '{$group}'\r\n";

            $fromDb = sprintf('oly_%s_%s', $account, $group);
            $fromColl = 'event.content.2014_Q4';

            $toDb = 'content_traffic_archive';
            $toColl = sprintf('%s_%s', $account, $group);

            $builder = new Builder($connection->selectCollection($toDb, $toColl));
            $result = $builder
                ->find()
                ->select('lastAccessed')
                ->exclude('_id')
                ->sort(['lastAccessed' => -1])
                ->limit(1)
                ->getQuery()
                ->getSingleResult()
            ;

            $ts = (null === $result) ? 0 : $result['lastAccessed']->sec;

            $limit = 200000;
            echo sprintf('Requesting %s records since %s from %s::%s on %s'."\r\n", number_format($limit), date('Y-m-d H:i:s', $ts), $fromDb, $fromColl, $conName);
            $builder = new Builder($connection->selectCollection($fromDb, $fromColl));
            $cursor = $builder
                ->find()
                ->field('createdAt')->gt(new \MongoDate($ts))
                ->field('action')->equals('view')
                ->sort(['createdAt' => 1])
                ->limit($limit)
                ->getQuery()
                ->execute()
            ;

            $count = $cursor->count(true);
            echo sprintf('Cursor generation complete. Found %s content events. Begin processing.'."\r\n", number_format($count));

            $page = $count / 100;
            echo sprintf('Beginning archive update in %s::%s. Found %s content events - Each \'.\' represents ~%s content ids'."\r\n", $toDb, $toColl, number_format($count), $page);
            echo str_repeat('-', 100)."\r\n";

            $processed = 0;
            $percentage = 0;
            $periods = 0;
            $skipped = 0;
            $upserted = 0;

            $sessionCollection = $sessionCon->selectCollection('content_session_archive', $toColl)->getMongoCollection();
            $collection = $connection->selectCollection($toDb, $toColl)->getMongoCollection();

            $cache = [];
            foreach ($cursor as $doc) {

                set_time_limit(10);

                // var_dump($doc);
                // die();



                $month = date('Y-m-01 00:00:00', $doc['createdAt']->sec);

                $cacheKey = strtotime($month);
                if (!isset($cache[$cacheKey])) {
                    $aggTime = microtime(true);
                    $ops = [
                        [
                            '$match'    => [
                                'month'     => new \MongoDate(strtotime($month))
                            ],
                        ],
                        [
                            '$group'    => [
                                '_id'       => [
                                    'contentId' => '$contentId',
                                    'userId'    => '$userId',
                                ],
                                'visits'    => ['$sum' => 1],
                            ],
                        ],
                    ];

                    echo sprintf('Aggregating visit data for month %s.'."\r\n", $month);

                    $agg = $sessionCollection->aggregate($ops);

                    if (!isset($agg['ok']) || $agg['ok'] != 1) {
                        echo sprintf('Aggregation FAILED: %s', serialize($agg));
                        die();
                    }

                    echo sprintf('Aggregation complete. Found %s records. Took %ss'."\r\n", number_format(count($agg['result'])), round(microtime(true) - $aggTime, 2));

                    $formatted = [];
                    foreach ($agg['result'] as $row) {
                        $key = sprintf('%s.%s', $row['_id']['contentId'], isset($row['_id']['userId']) ? (String) $row['_id']['userId'] : 'anon');
                        $formatted[$key] = $row['visits'];
                    }

                    $cache[$cacheKey] = $formatted;
                    unset($formatted);
                    unset($agg);
                }

                $contentId = (int) $doc['clientId'];

                $eventKey = sprintf('%s.%s', $contentId, isset($doc['userId']) ? (String) $doc['userId'] : 'anon');
                $visits = isset($cache[$cacheKey][$eventKey]) ? $cache[$cacheKey][$eventKey] : 1;

                $criteria = [
                    'metadata.month'        => new \MongoDate(strtotime($month)),
                    'metadata.contentId'    => $contentId,
                    'metadata.userId'       => isset($doc['userId']) ? $doc['userId'] : ['$exists' => false],
                ];


                $metadata = [
                    'month'     => new \MongoDate(strtotime($month)),
                    'contentId' => $contentId,
                ];

                if (isset($doc['userId'])) {
                    $metadata['userId'] = $doc['userId'];
                }

                $newObj = [
                    '$setOnInsert'  => ['metadata' => $metadata],
                    '$set'          => [
                        'lastAccessed'  => $doc['createdAt'],
                        'visits'        => $visits,
                    ],
                    '$inc'              => [
                        'pageviews' => 1,
                    ],
                ];

                try {
                    $collection->update($criteria, $newObj, ['upsert' => true]);
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
            unset($cache);

            if ($periods < 100) {
                echo str_repeat('.', 100 - $periods);
            }
            echo "\r\n";

            $time = round(microtime(true) - $start, 3);
            $recsPerSec = round($upserted / $time, 2);
            echo str_repeat('-', 100)."\r\n";
            echo sprintf('Upsert complete for %s. Took %ss for %s recs/sec %s'."\r\n\r\n", $group, $time, $recsPerSec, $this->getMemoryUsage());

            // die();

        }
        die();
    }

    protected function getMemoryUsage()
    {
        $memory = round(memory_get_usage() / (1024 * 1024)); // to get usage in Mo
        $memoryMax = round(memory_get_peak_usage() / (1024 * 1024)); // to get max usage in Mo

        return sprintf('(RAM : current=%uMo peak=%uMo)', $memory, $memoryMax);
    }

    public function getGroups()
    {
        $query = $this->get('request')->query;
        $groups = $query->get('groups');
        if (empty($groups) || 'all' === $groups) {
            $groups = $this->groups;
        } else {
            $groups = explode(',',$groups);
        }
        return $groups;
    }



    public function appendUserIdsToEventsAction()
    {

        die('done');
        $this->init();

        $groups = $this->getGroups();

        $conName = 'doctrine_mongodb.odm.olytics_connection';
        $connection = $this->get($conName);

        foreach ($groups as $group) {

            if (!in_array($group, $this->groups)) {
                echo sprintf('Group "%s" not found as a valid group. Skipping.'."\r\n\r\n", $group);
                continue;
            }

            $start = microtime(true);

            echo "Converting user ids for group '{$group}'\r\n";

            $account = (in_array($group, ['fcp', 'fl', 'ooh', 'sdce'])) ? 'acbm' : 'cygnus';
            $dbName = sprintf('oly_%s_%s_events', $account, $group);
            $collName = 'content';

            echo "Finding all Omeda customerIds in Olytics {$dbName}::{$collName} on {$conName}\r\n";

            $builder = new Builder($connection->selectCollection($dbName, $collName));
            $cursor = $builder
                ->find()
                ->field('session.customerId')->type(2)
                ->where('this.session.customerId.length == 15')
                ->select('session.customerId')
                ->exclude('_id')
                ->getQuery()
                ->execute();
            ;

            echo sprintf("Cursor generation complete. Found %s records with Omeda customer ids.\r\n", number_format($cursor->count(true)));

            $omedaIds = [];
            foreach ($cursor as $doc) {
                $omedaId = $doc['session']['customerId'];
                $omedaIds[$omedaId] = true;
            }

            echo sprintf('Omeda customer ids consolidated. Found %s distinct Omeda customer ids.'."\r\n", number_format(count($omedaIds)));

            echo "Querying Merrick users with these Omeda customer ids.\r\n";

            $builder = new Builder($this->getUserCollection());

            $cursor = $builder
                ->find()
                ->field('site')->equals($this->sites[$group])
                ->field('omeda_encrypted_id')->in(array_keys($omedaIds))
                ->select('_id', 'omeda_encrypted_id')
                ->getQuery()
                ->execute()
            ;

            $page = count($cursor) / 100;

            echo sprintf("Cursor generation complete. Found %s Merrick user ids.\r\n", number_format(count($cursor)));
            echo sprintf('Updating events in %s::%s - Each \'.\' represents ~%s user ids'."\r\n", $dbName, $collName, $page);
            echo str_repeat('-', 100) . "\r\n";

            $collection = $connection->selectCollection($dbName, $collName)->getMongoCollection();

            $processed = 0;
            $percentage = 0;
            $periods = 0;
            $skipped = 0;
            $upserted = 0;

            foreach ($cursor as $doc) {
                set_time_limit(10);

                // unset so we can handle diff
                $omedaId = $doc['omeda_encrypted_id'];
                if (isset($omedaIds[$omedaId])) {
                    unset($omedaIds[$omedaId]);
                }

                $criteria = [
                    'session.customerId' => $omedaId,
                ];

                $newObj = [
                    '$set'  => [
                        'session.customerId' => $doc['_id'],
                    ],
                ];

                $processed++;
                $increment = 1 / $page;
                $floored = floor($increment);
                $percentage += $increment;

                try {
                    $collection->update($criteria, $newObj, ['multiple' => true]);
                    $upserted++;
                } catch (\Exception $e) {
                    echo sprintf('ERRORS FOUND :: %s', $e->getMessage());
                    die();
                }

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
            echo "\r\n";
            echo str_repeat('-', 100)."\r\n";

            echo sprintf('An additional %s Omeda customer ids were found that were invalid. Unsetting.'."\r\n", count($omedaIds));

            if (!empty($omedaIds)) {
                $criteria = ['session.customerId' => ['$in' => array_keys($omedaIds)]];
                $newObj = ['$set' => ['session.customerId' => null]];
                try {
                    $collection->update($criteria, $newObj, ['multiple' => true]);
                } catch (\Exception $e) {
                    echo sprintf('ERRORS FOUND :: %s', $e->getMessage());
                    die();
                }
            }

            $time = round(microtime(true) - $start, 3);
            $recsPerSec = round($upserted / $time, 2);
            echo str_repeat('-', 100)."\r\n";
            echo sprintf('Upsert complete for %s. Took %ss for %s ids/sec %s'."\r\n\r\n", $group, $time, $recsPerSec, $this->getMemoryUsage());

            unset($omedaIds);
            unset($cursor);

            $start = microtime(true);

            echo "Finding all string Mongo customerIds in Olytics {$dbName}::{$collName} on {$conName}\r\n";

            $builder = new Builder($connection->selectCollection($dbName, $collName));
            $cursor = $builder
                ->find()
                ->field('session.customerId')->type(2)
                ->where('this.session.customerId.length == 24')
                ->select('session.customerId')
                ->exclude('_id')
                ->getQuery()
                ->execute();
            ;

            echo sprintf("Cursor generation complete. Found %s string Mongo ids.\r\n", number_format(count($cursor)));

            $stringIds = [];
            foreach ($cursor as $doc) {
                $id = $doc['session']['customerId'];
                $stringIds[$id] = true;
            }

            echo sprintf('String Mongo customer ids consolidated. Found %s distinct Mongo customer ids.'."\r\n", number_format(count($stringIds)));

            $page = count($stringIds) / 100;

            echo sprintf('Updating events in %s::%s - Each \'.\' represents ~%s user ids'."\r\n", $dbName, $collName, $page);
            echo str_repeat('-', 100) . "\r\n";

            $processed = 0;
            $percentage = 0;
            $periods = 0;
            $skipped = 0;
            $upserted = 0;

            $invalid = [];
            foreach ($stringIds as $customerId => $set) {

                set_time_limit(10);

                try {
                    $mongoId = new \MongoId($customerId);
                } catch (\Exception $e) {
                    $invalid[$customerId] = true;
                    continue;
                }

                $criteria = [
                    'session.customerId' => $customerId,
                ];

                $newObj = [
                    '$set'  => [
                        'session.customerId' => $mongoId,
                    ],
                ];

                $processed++;
                $increment = 1 / $page;
                $floored = floor($increment);
                $percentage += $increment;

                try {
                    $collection->update($criteria, $newObj, ['multiple' => true]);
                    $upserted++;
                } catch (\Exception $e) {
                    echo sprintf('ERRORS FOUND :: %s', $e->getMessage());
                    die();
                }

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
            echo "\r\n";
            echo str_repeat('-', 100)."\r\n";

            echo sprintf('An additional %s string Mongo ids were found that were invalid. Unsetting.'."\r\n", count($invalid));

            if (!empty($invalid)) {
                $criteria = ['session.customerId' => ['$in' => array_keys($invalid)]];
                $newObj = ['$set' => ['session.customerId' => null]];
                try {
                    $collection->update($criteria, $newObj, ['multiple' => true]);
                } catch (\Exception $e) {
                    echo sprintf('ERRORS FOUND :: %s', $e->getMessage());
                    die();
                }
            }
            unset($stringIds);
            unset($invalid);

            $time = round(microtime(true) - $start, 3);
            $recsPerSec = round($upserted / $time, 2);
            echo str_repeat('-', 100)."\r\n";
            echo sprintf('Upsert complete for %s. Took %ss for %s ids/sec %s'."\r\n\r\n", $group, $time, $recsPerSec, $this->getMemoryUsage());
        }
        die();
    }

    public function appendUserIdsAction()
    {
        die('done');
        $this->init();

        $query = $this->get('request')->query;

        $groups = $query->get('groups');
        if (empty($groups) || 'all' === $groups) {
            $groups = $this->groups;
        } else {
            $groups = explode(',',$groups);
        }

        $conn = $query->get('conn');
        if (!in_array($conn, ['db9', 'olytics'])) {
            echo 'Invalid connection specified.';
            die();
        }

        foreach ($groups as $group) {

            if (!in_array($group, $this->groups)) {
                echo sprintf('Group "%s" not found as a valid group. Skipping.'."\r\n\r\n", $group);
                continue;
            }

            echo "Appending user ids for group '{$group}'\r\n";

            $account = (in_array($group, ['fcp', 'fl', 'ooh', 'sdce'])) ? 'acbm' : 'cygnus';
            $dbName = sprintf('oly_%s_%s', $account, $group);
            $conName = 'doctrine_mongodb.odm.'.$conn.'_connection';

            $connection = $this->get($conName);

            foreach ($this->quarters as $quarter) {
                $collName = sprintf('session.%s', $quarter);
                echo "Reading session data from Olytics {$dbName}::{$collName} on {$conName}\r\n";

                $builder = new Builder($connection->selectCollection($dbName, $collName));

                $cursor = $builder
                    ->distinct('customerId')
                    ->getQuery()
                    ->execute()
                ;

                $customerIds = [];
                foreach ($cursor as $id) {
                    if (is_string($id)) {
                        $customerIds[] = $id;
                    }
                }

                echo sprintf("Cursor generation complete. Found %s distinct customer ids.\r\n", number_format(count($customerIds)));

                echo "Querying Merrick users with these customer ids.\r\n";

                $builder = new Builder($this->getUserCollection());

                $cursor = $builder
                    ->find()
                    ->field('site')->equals($this->sites[$group])
                    ->field('omeda_encrypted_id')->in($customerIds)
                    ->select('_id', 'omeda_encrypted_id')
                    ->getQuery()
                    ->execute()
                ;

                $page = count($cursor) / 100;

                echo sprintf("Cursor generation complete. Found %s Merrick user ids.\r\n", number_format(count($cursor)));
                echo sprintf('Updating sessions in %s::%s - Each \'.\' represents ~%s user ids'."\r\n", $dbName, $collName, $page);
                echo str_repeat('-', 100) . "\r\n";

                $collection = $connection->selectCollection($dbName, $collName)->getMongoCollection();

                $processed = 0;
                $percentage = 0;
                $periods = 0;
                $skipped = 0;
                $upserted = 0;

                foreach ($cursor as $user) {

                    $criteria = [
                        'customerId'=> $user['omeda_encrypted_id'],
                        'userId'    => ['$exists' => false],
                    ];

                    $newObj = [
                        '$set'  => [
                            'userId'    => $user['_id'],
                        ]
                    ];

                    $options = [
                        'multiple'  => true,
                    ];

                    $processed++;
                    $increment = 1 / $page;
                    $floored = floor($increment);
                    $percentage += $increment;

                    try {
                        $collection->update($criteria, $newObj, $options);
                        $upserted++;
                    } catch (\Exception $e) {
                        echo sprintf('ERRORS FOUND :: %s', $e->getMessage());
                        die();
                    }

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
                echo "\r\n";

            }

        }

        // var_dump($this->getUserCollection());
        die();
    }



    protected function getUserCollection()
    {
        return $this->get('doctrine_mongodb.odm.merrick_connection')->selectCollection('merrick', 'users_v2');
    }
}