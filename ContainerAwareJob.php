<?php

namespace BCC\ResqueBundle;

use BCC\ResqueBundle\Entity\ResqueJob;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraints\DateTime;

abstract class ContainerAwareJob extends Job
{
    /**
     * @var KernelInterface
     */
    private $kernel = null;

    /**
     * @var ResqueJob
     */
    public $resqueJob = null;

    /**
     * @return ContainerInterface
     */
    protected function getContainer()
    {
        if ($this->kernel === null) {
            $this->kernel = $this->createKernel();
            $this->kernel->boot();
        }

        return $this->kernel->getContainer();
    }

    public function setKernelOptions(array $kernelOptions)
    {
        $this->args = \array_merge($this->args, $kernelOptions);
    }

    /**
     * @return KernelInterface
     */
    protected function createKernel()
    {
        $finder = new Finder();
        $finder->name('*Kernel.php')->depth(0)->in($this->args['kernel.root_dir']);
        $results = iterator_to_array($finder);
        $file = current($results);
        $class = $file->getBasename('.php');

        require_once $file;

        return new $class(
            isset($this->args['kernel.environment']) ? $this->args['kernel.environment'] : 'dev',
            isset($this->args['kernel.debug']) ? $this->args['kernel.debug'] : true
        );
    }

    public function setUp()
    {
        /** @var $registry \Symfony\Bridge\Doctrine\RegistryInterface */
        $registry = $this->getContainer()->get('doctrine');

        $class = \Doctrine\Common\Util\ClassUtils::getClass(new ResqueJob());
        $em = $registry->getManagerForClass($class);


        /** @var \BCC\ResqueBundle\Entity\ResqueJob $resqueJobRepository */
        $resqueJobRepository = $em->getRepository('BCCResqueBundle:ResqueJob');

        $jobId = $this->job->payload['id'];

        /** @var \BCC\ResqueBundle\Entity\ResqueJob $resqueJob */
        $resqueJob = $resqueJobRepository->findOneByResqueUUID($jobId);

        if(!is_null($resqueJob)) {
            $resqueJob->setState(ResqueJob::STATE_RUNNING);
            $resqueJob->setStartedAt(new DateTime('now'));
            $em->persist($resqueJob);
            $em->flush();
        }
    }

    public function tearDown()
    {
        /** @var $registry \Symfony\Bridge\Doctrine\RegistryInterface */
        $registry = $this->getContainer()->get('doctrine');

        $class = \Doctrine\Common\Util\ClassUtils::getClass(new ResqueJob());
        $em = $registry->getManagerForClass($class);


        /** @var \BCC\ResqueBundle\Entity\ResqueJob $resqueJobRepository */
        $resqueJobRepository = $em->getRepository('BCCResqueBundle:ResqueJob');

        $jobId = $this->job->payload['id'];

        $resqueJob = $resqueJobRepository->findOneByResqueUUID($jobId);

        if(!is_null($resqueJob)) {
            $resqueJob->setState(ResqueJob::STATE_FINISHED);
            $em->persist($resqueJob);
            $em->flush();
        }

        if ($this->kernel) {
            $this->kernel->shutdown();
        }
    }
}
