<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 2/12/15
 * Time: 4:14 PM
 */

namespace BCC\ResqueBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpKernel\Exception\FlattenException;

/**
 * @ORM\Entity(repositoryClass = "BCC\ResqueBundle\Entity\Repository\ResqueJobRepository")
 * @ORM\Table(name = "resque_jobs", indexes = {
 * @ORM\Index("sorting_index", columns = {"state", "id"}),
 * })
 * @ORM\ChangeTrackingPolicy("DEFERRED_EXPLICIT")
 *
 * @author Daniel Brooks <daniel_brooks@dabsquared.com>
 */
class ResqueJob {


    const STATE_PENDING = 'pending';
    /** State if job was started and has not exited, yet. */
    const STATE_RUNNING = 'running';
    /** State if job exists with a successful exit code. */
    const STATE_FINISHED = 'finished';
    /** State if job exits with a non-successful exit code. */
    const STATE_FAILED = 'failed';



    /** @ORM\Id @ORM\GeneratedValue(strategy = "AUTO") @ORM\Column(type = "bigint", options = {"unsigned": true}) */
    private $id;

    /** @ORM\Column(type = "text", nullable=true) */
    private $resqueUUID;

    /** @ORM\Column(type = "string", length = 15) */
    private $state;

    /** @ORM\Column(type = "string", length = 255) */
    private $queue;

    /** @ORM\Column(type = "datetime", name="createdAt") */
    private $createdAt;

    /** @ORM\Column(type = "datetime", name="startedAt", nullable = true) */
    private $startedAt;

    /** @ORM\Column(type = "datetime", name="executeAfter", nullable = true) */
    private $executeAfter;

    /** @ORM\Column(type = "datetime", name="closedAt", nullable = true) */
    private $closedAt;

    /**
     * @ORM\ManyToMany(targetEntity = "ResqueJob", fetch = "EAGER")
     * @ORM\JoinTable(name="resque_job_dependencies",
     *     joinColumns = { @ORM\JoinColumn(name = "source_job_id", referencedColumnName = "id") },
     *     inverseJoinColumns = { @ORM\JoinColumn(name = "dest_job_id", referencedColumnName = "id")}
     * )
     */
    private $dependencies;

    /** @ORM\Column(type = "text", nullable = true) */
    private $output;

    /** @ORM\Column(type = "text", name="errorOutput", nullable = true) */
    private $errorOutput;

    /** @ORM\Column(type = "smallint", name="exitCode", nullable = true, options = {"unsigned": true}) */
    private $exitCode;

    /** @ORM\Column(type = "smallint", name="maxRuntime", options = {"unsigned": true}) */
    private $maxRuntime = 0;

    /** @ORM\Column(type = "smallint", name="maxRetries", options = {"unsigned": true}) */
    private $maxRetries = 0;

    /**
     * @ORM\ManyToOne(targetEntity = "ResqueJob", inversedBy = "retryJobs")
     * @ORM\JoinColumn(name="originalJob_id", referencedColumnName="id")
     */
    private $originalJob;

    /** @ORM\OneToMany(targetEntity = "ResqueJob", mappedBy = "originalJob", cascade = {"persist", "remove", "detach"}) */
    private $retryJobs;

    /** @ORM\Column(type = "smallint", nullable = true, options = {"unsigned": true}) */
    private $runtime;

    /**
     * This may store any entities which are related to this job, and are
     * managed by Doctrine.
     *
     * It is effectively a many-to-any association.
     */
    private $relatedEntities;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->dependencies = new ArrayCollection();
        $this->retryJobs = new ArrayCollection();
        $this->relatedEntities = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param mixed $state
     */
    public function setState($state)
    {
        $this->state = $state;
    }

    /**
     * @return mixed
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param mixed $queue
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param mixed $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return mixed
     */
    public function getStartedAt()
    {
        return $this->startedAt;
    }

    /**
     * @param mixed $startedAt
     */
    public function setStartedAt($startedAt)
    {
        $this->startedAt = $startedAt;
    }

    /**
     * @return mixed
     */
    public function getExecuteAfter()
    {
        return $this->executeAfter;
    }

    /**
     * @param mixed $executeAfter
     */
    public function setExecuteAfter($executeAfter)
    {
        $this->executeAfter = $executeAfter;
    }

    /**
     * @return mixed
     */
    public function getClosedAt()
    {
        return $this->closedAt;
    }

    /**
     * @param mixed $closedAt
     */
    public function setClosedAt($closedAt)
    {
        $this->closedAt = $closedAt;
    }

    /**
     * @return mixed
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @param mixed $output
     */
    public function setOutput($output)
    {
        $this->output = $output;
    }

    /**
     * @return mixed
     */
    public function getErrorOutput()
    {
        return $this->errorOutput;
    }

    /**
     * @param mixed $errorOutput
     */
    public function setErrorOutput($errorOutput)
    {
        $this->errorOutput = $errorOutput;
    }

    /**
     * @return mixed
     */
    public function getExitCode()
    {
        return $this->exitCode;
    }

    /**
     * @param mixed $exitCode
     */
    public function setExitCode($exitCode)
    {
        $this->exitCode = $exitCode;
    }

    /**
     * @return mixed
     */
    public function getMaxRuntime()
    {
        return $this->maxRuntime;
    }

    /**
     * @param mixed $maxRuntime
     */
    public function setMaxRuntime($maxRuntime)
    {
        $this->maxRuntime = $maxRuntime;
    }

    /**
     * @return mixed
     */
    public function getRuntime()
    {
        return $this->runtime;
    }

    /**
     * @param mixed $runtime
     */
    public function setRuntime($runtime)
    {
        $this->runtime = $runtime;
    }

    /**
     * @return mixed
     */
    public function getResqueUUID()
    {
        return $this->resqueUUID;
    }

    /**
     * @param mixed $resqueUUID
     */
    public function setResqueUUID($resqueUUID)
    {
        $this->resqueUUID = $resqueUUID;
    }

    /**
     * @return ArrayCollection
     */
    public function getRelatedEntities()
    {
        return $this->relatedEntities;
    }

    /**
     * @param $class
     * @return mixed|null
     */
    public function findRelatedEntity($class)
    {
        foreach ($this->relatedEntities as $entity) {
            if ($entity instanceof $class) {
                return $entity;
            }
        }
        return null;
    }

    /**
     * @param $entity
     */
    public function addRelatedEntity($entity)
    {
        assert('is_object($entity)');
        if ($this->relatedEntities->contains($entity)) {
            return;
        }
        $this->relatedEntities->add($entity);
    }

    /**
     * @return ArrayCollection
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }

    /**
     * @param ResqueJob $job
     * @return bool
     */
    public function hasDependency(ResqueJob $job)
    {
        return $this->dependencies->contains($job);
    }

    /**
     * @param ResqueJob $job
     */
    public function addDependency(ResqueJob $job)
    {
        if ($this->dependencies->contains($job)) {
            return;
        }
        if ($this->mightHaveStarted()) {
            throw new \LogicException('You cannot add dependencies to a job which might have been started already.');
        }
        $this->dependencies->add($job);
    }

    /**
     * @return int
     */
    public function getMaxRetries()
    {
        return $this->maxRetries;
    }

    /**
     * @param $tries
     */
    public function setMaxRetries($tries)
    {
        $this->maxRetries = (integer) $tries;
    }

    /**
     * @return bool
     */
    public function isRetryAllowed()
    {
        // If no retries are allowed, we can bail out directly, and we
        // do not need to initialize the retryJobs relation.
        if (0 === $this->maxRetries) {
            return false;
        }
        return count($this->retryJobs) < $this->maxRetries;
    }

    /**
     * @return $this
     */
    public function getOriginalJob()
    {
        if (null === $this->originalJob) {
            return $this;
        }
        return $this->originalJob;
    }

    /**
     * @param ResqueJob $job
     */
    public function setOriginalJob(ResqueJob $job)
    {
        if (self::STATE_PENDING !== $this->state) {
            throw new \LogicException($this.' must be in state "PENDING".');
        }
        if (null !== $this->originalJob) {
            throw new \LogicException($this.' already has an original job set.');
        }
        $this->originalJob = $job;
    }

    /**
     * @param ResqueJob $job
     */
    public function addRetryJob(ResqueJob $job)
    {
        if (self::STATE_RUNNING !== $this->state) {
            throw new \LogicException('Retry jobs can only be added to running jobs.');
        }
        $job->setOriginalJob($this);
        $this->retryJobs->add($job);
    }

    /**
     * @return ArrayCollection
     */
    public function getRetryJobs()
    {
        return $this->retryJobs;
    }

    /**
     * @return bool
     */
    public function isRetryJob()
    {
        return null !== $this->originalJob;
    }

    /**
     * @return bool
     */
    private function mightHaveStarted()
    {
        if (null === $this->id) {
            return false;
        }
        if (self::STATE_NEW === $this->state) {
            return false;
        }
        if (self::STATE_PENDING === $this->state && ! $this->isStartable()) {
            return false;
        }
        return true;
    }

}