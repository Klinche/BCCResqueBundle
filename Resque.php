<?php

namespace BCC\ResqueBundle;

use BCC\ResqueBundle\Entity\ResqueJob;
use Psr\Log\NullLogger;

class Resque
{
    /**
     * @var array
     */
    private $kernelOptions;

    /**
     * @var array
     */
    private $redisConfiguration;

    /**
     * @var array
     */
    private $globalRetryStrategy = array();

    /**
     * @var array
     */
    private $jobRetryStrategy = array();

    /**
     * @var $registry \Symfony\Bridge\Doctrine\RegistryInterface
     */
    public $registry;

    public function __construct(array $kernelOptions, $registry)
    {
        $this->kernelOptions = $kernelOptions;
        $this->registry = $registry;
    }

    public function setPrefix($prefix)
    {
        \Resque_Redis::prefix($prefix);
    }

    public function setRedisConfiguration($host, $port, $database)
    {
        $this->redisConfiguration = array(
            'host'     => $host,
            'port'     => $port,
            'database' => $database,
        );
        $host = substr($host, 0, 1) == '/' ? $host : $host.':'.$port;

        \Resque::setBackend($host, $database);
    }

    public function setGlobalRetryStrategy($strategy)
    {
        $this->globalRetryStrategy = $strategy;
    }

    public function setJobRetryStrategy($strategy)
    {
        $this->jobRetryStrategy = $strategy;
    }

    public function getRedisConfiguration()
    {
        return $this->redisConfiguration;
    }

    public function enqueue(Job $job)
    {
        if ($job instanceof ContainerAwareJob) {
            $job->setKernelOptions($this->kernelOptions);
            $job->setBCCJobId($this->generateBCCResqueGUID());
        }



        $this->attachRetryStrategy($job);

        $resqueJob = new ResqueJob($job->getBCCJobId(), \get_class($job), $job->queue, $job->args);

        $em = $this->registry->getManagerForClass('BCCResqueBundle:ResqueJob');

        $em->persist($resqueJob);
        $em->flush();

        $result = \Resque::enqueue($job->queue, \get_class($job), $job->args, true);

        $resqueJob->setResqueStatusUUID($result);
        $em->persist($resqueJob);
        $em->flush();

        return $resqueJob;
    }

    public function enqueueOnce(Job $job, $trackStatus = false)
    {
        $queue = new Queue($job->queue);
        $jobs  = $queue->getJobs();

        foreach ($jobs AS $j) {
            if ($j->job->payload['class'] == get_class($job)) {
                if (count(array_intersect($j->args, $job->args)) == count($job->args)) {
                    return ($trackStatus) ? $j->job->payload['id'] : null;
                }
            }
        }

        return $this->enqueue($job, $trackStatus);
    }

    public function enqueueAt($at,Job $job)
    {
        if ($job instanceof ContainerAwareJob) {
            $job->setKernelOptions($this->kernelOptions);
            $job->setBCCJobId($this->generateBCCResqueGUID());
        }

        $this->attachRetryStrategy($job);

        $resqueJob = new ResqueJob($job->getBCCJobId(), \get_class($job), $job->queue, $job->args, $at);

        $em = $this->registry->getManagerForClass('BCCResqueBundle:ResqueJob');

        $em->persist($resqueJob);
        $em->flush();

        \ResqueScheduler::enqueueAt($at, $job->queue, \get_class($job), $job->args);

        return $resqueJob;
    }

    public function enqueueIn($in,Job $job)
    {
        if ($job instanceof ContainerAwareJob) {
            $job->setKernelOptions($this->kernelOptions);
        }

        $this->attachRetryStrategy($job);

        $resqueJob = new ResqueJob($job, time() + $in);

        $em = $this->registry->getManagerForClass('BCCResqueBundle:ResqueJob');

        $em->persist($resqueJob);
        $em->flush();

        \ResqueScheduler::enqueueIn($in, $job->queue, \get_class($job), $job->args);

        return null;
    }

    public function removedDelayed(Job $job)
    {
        if ($job instanceof ContainerAwareJob) {
            $job->setKernelOptions($this->kernelOptions);
        }

        $this->attachRetryStrategy($job);

        return \ResqueScheduler::removeDelayed($job->queue, \get_class($job),$job->args);
    }

    public function removeFromTimestamp($at, Job $job)
    {
        if ($job instanceof ContainerAwareJob) {
            $job->setKernelOptions($this->kernelOptions);
        }

        $this->attachRetryStrategy($job);

        return \ResqueScheduler::removeDelayedJobFromTimestamp($at, $job->queue, \get_class($job), $job->args);
    }

    public function getQueues()
    {
        return \array_map(function ($queue) {
            return new Queue($queue);
        }, \Resque::queues());
    }

    /**
     * @param $queue
     * @return Queue
     */
    public function getQueue($queue)
    {
        return new Queue($queue);
    }

    public function getWorkers()
    {
        return \array_map(function ($worker) {
            return new Worker($worker);
        }, \Resque_Worker::all());
    }

    public function getWorker($id)
    {
        $worker = \Resque_Worker::find($id);

        if (!$worker) {
            return null;
        }

        return new Worker($worker);
    }

    public function pruneDeadWorkers()
    {
        // HACK, prune dead workers, just in case
        $worker = new \Resque_Worker('temp');
        $worker->setLogger(new NullLogger());
        $worker->pruneDeadWorkers();
    }

    public function getDelayedJobTimestamps()
    {
        $timestamps= \Resque::redis()->zrange('delayed_queue_schedule', 0, -1);

        //TODO: find a more efficient way to do this
        $out=array();
        foreach ($timestamps as $timestamp) {
            $out[]=array($timestamp,\Resque::redis()->llen('delayed:'.$timestamp));
        }

        return $out;
    }

    public function getFirstDelayedJobTimestamp()
    {
        $timestamps=$this->getDelayedJobTimestamps();
        if (count($timestamps)>0) {
            return $timestamps[0];
        }

        return array(null,0);
    }

    public function getNumberOfDelayedJobs()
    {
        return \ResqueScheduler::getDelayedQueueScheduleSize();
    }

    public function getJobsForTimestamp($timestamp)
    {
        $jobs= \Resque::redis()->lrange('delayed:'.$timestamp,0, -1);
        $out=array();
        foreach ($jobs as $job) {
            $out[]=json_decode($job, true);
        }

        return $out;
    }

    /**
     * @param $queue
     * @return int
     */
    public function clearQueue($queue)
    {
        $length=\Resque::redis()->llen('queue:'.$queue);
        \Resque::redis()->del('queue:'.$queue);

        return $length;
    }

    public function getFailedJobs($start = -100, $count = 100)
    {
        $jobs = \Resque::redis()->lrange('failed', $start, $count);

        $result = array();

        foreach ($jobs as $job) {
            $result[] = new FailedJob(json_decode($job, true));
        }

        return $result;
    }

    /**
     * @param $jobId
     * @return ResqueJob
     */
    public function getJob($jobId)
    {
        $em = $this->registry->getManagerForClass('BCCResqueBundle:ResqueJob');

        /** @var \BCC\ResqueBundle\Entity\Repository\ResqueJobRepository $resqueJobRepository */
        $resqueJobRepository = $em->getRepository('BCCResqueBundle:ResqueJob');

        /** @var \BCC\ResqueBundle\Entity\ResqueJob $job */
        $job = $resqueJobRepository->findOneByBCCUUID($jobId);

        return $job;
    }


    /**
     * Attach any applicable retry strategy to the job.
     *
     * @param Job $job
     */
    protected function attachRetryStrategy($job)
    {
        $class = get_class($job);

        if (isset($this->jobRetryStrategy[$class])) {
            if (count($this->jobRetryStrategy[$class])) {
                $job->args['bcc_resque.retry_strategy'] = $this->jobRetryStrategy[$class];
            }
            $job->args['bcc_resque.retry_strategy'] = $this->jobRetryStrategy[$class];
        } elseif (count($this->globalRetryStrategy)) {
            $job->args['bcc_resque.retry_strategy'] = $this->globalRetryStrategy;
        }
    }

    /**
     * @return string
     */
    static function generateBCCResqueGUID() {
        return uniqid("bcc_resque.job_id", true);
    }


    #region "Events"


    public function beforePerform(\Resque_Job $job)
    {
        $args = $job->getArguments();
        $jobId = $args['bcc_resque.job_id'];

        $em = $this->registry->getManagerForClass('BCCResqueBundle:ResqueJob');

        /** @var \BCC\ResqueBundle\Entity\Repository\ResqueJobRepository $resqueJobRepository */
        $resqueJobRepository = $em->getRepository('BCCResqueBundle:ResqueJob');

        /** @var \BCC\ResqueBundle\Entity\ResqueJob $resqueJob */
        $resqueJob = $resqueJobRepository->findOneByBCCUUID($jobId);

        if(!is_null($resqueJob)) {
            $resqueJob->setState(ResqueJob::STATE_RUNNING);
            $resqueJob->setStartedAt(new \DateTime('now'));
            $em->persist($resqueJob);
            $em->flush();
        }
    }

    public function afterPerform(\Resque_Job $job)
    {
        $args = $job->getArguments();
        $jobId = $args['bcc_resque.job_id'];

        $em = $this->registry->getManagerForClass('BCCResqueBundle:ResqueJob');

        /** @var \BCC\ResqueBundle\Entity\Repository\ResqueJobRepository $resqueJobRepository */
        $resqueJobRepository = $em->getRepository('BCCResqueBundle:ResqueJob');

        /** @var \BCC\ResqueBundle\Entity\ResqueJob $resqueJob */
        $resqueJob = $resqueJobRepository->findOneByBCCUUID($jobId);

        if(!is_null($resqueJob)) {
            $resqueJob->setState(ResqueJob::STATE_FINISHED);
            $resqueJob->setClosedAt(new \DateTime("now"));
            $em->persist($resqueJob);
            $em->flush();
        }
    }

    public function onFailure(\Exception $exception, \Resque_Job $job)
    {

        $args = $job->getArguments();

        if (empty($args['bcc_resque.retry_strategy'])) {
            return;
        }

        if (!isset($args['bcc_resque.retry_attempt'])) {
            $args['bcc_resque.retry_attempt'] = 0;
        }

        $backoff = $args['bcc_resque.retry_strategy'];
        if (!isset($backoff[$args['bcc_resque.retry_attempt']])) {
            return;
        }

        $delay = $backoff[$args['bcc_resque.retry_attempt']];
        $args['bcc_resque.retry_attempt']++;

        $oldJobId = $args['bcc_resque.job_id'];

        $args['bcc_resque.job_id'] = $this->generateBCCResqueGUID();

        $resqueJob = new ResqueJob($args['bcc_resque.job_id'], $job->payload['class'], $job->queue, $args);

        $em = $this->registry->getManagerForClass('BCCResqueBundle:ResqueJob');

        /** @var \BCC\ResqueBundle\Entity\Repository\ResqueJobRepository $resqueJobRepository */
        $resqueJobRepository = $em->getRepository('BCCResqueBundle:ResqueJob');

        /** @var \BCC\ResqueBundle\Entity\ResqueJob $resqueJob */
        $oldResqueJob = $resqueJobRepository->findOneByBCCUUID($oldJobId);

        if(!is_null($oldResqueJob)) {
            $resqueJob->setOriginalJob($oldResqueJob);
            $oldResqueJob->setState(ResqueJob::STATE_FAILED);
            $oldResqueJob->setClosedAt(new \DateTime("now"));
            $oldResqueJob->setErrorOutput($exception->getTraceAsString());
            $em->persist($oldResqueJob);
        }

        if ($delay == 0) {
            $em->persist($resqueJob);
            $em->flush();

            $result = \Resque::enqueue($job->queue, $job->payload['class'], $args, true);

            $resqueJob->setResqueStatusUUID($result);


            $em->persist($resqueJob);
            $em->flush();

//            $logger->log(Psr\Log\LogLevel::ERROR, 'Job failed. Auto re-queued, attempt number: {attempt}', array(
//                    'attempt' => $args['bcc_resque.retry_attempt'] - 1)
//            );
        } else {
            $at = time() + $delay;

            $resqueJob->setExecuteAfter($at);
            $em->persist($resqueJob);
            $em->flush();

            \ResqueScheduler::enqueueAt($at, $job->queue, $job->payload['class'], $args);


//            $logger->log(Psr\Log\LogLevel::ERROR, 'Job failed. Auto re-queued. Scheduled for: {timestamp}, attempt number: {attempt}', array(
//                'timestamp' => date('Y-m-d H:i:s', $at),
//                'attempt'   => $args['bcc_resque.retry_attempt'] - 1,
//            ));
        }
    }

    #endregion


}
