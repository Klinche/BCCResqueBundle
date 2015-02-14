<?php

namespace BCC\ResqueBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use JMS\DiExtraBundle\Annotation as DI;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\View\TwitterBootstrapView;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DefaultController extends Controller
{

    /** @DI\Inject("doctrine") */
    private $registry;

    /** @DI\Inject */
    private $request;

    /** @DI\Inject */
    private $router;

    public function indexAction()
    {
        $this->getResque()->pruneDeadWorkers();
        
        return $this->render(
            'BCCResqueBundle:Default:index.html.twig',
            array(
                'resque' => $this->getResque(),
            )
        );
    }

    public function listDatabaseAction()
    {
        $qb = $this->getEm()->createQueryBuilder();
        $qb->select('j')->from('BCCResqueBundle:ResqueJob', 'j')
            ->where($qb->expr()->isNull('j.originalJob'))
            ->orderBy('j.id', 'desc');

        $pager = new Pagerfanta(new DoctrineORMAdapter($qb));
        $pager->setCurrentPage(max(1, (integer) $this->request->query->get('page', 1)));
        $pager->setMaxPerPage(max(5, min(50, (integer) $this->request->query->get('per_page', 20))));

        $pagerView = new TwitterBootstrapView();
        $router = $this->router;
        $routeGenerator = function($page) use ($router, $pager) {
            return $router->generate('BCCResqueBundle_all_database_list', array('page' => $page, 'per_page' => $pager->getMaxPerPage()));
        };


        return $this->render(
            'BCCResqueBundle:Default:all_database.html.twig',
            array(
                'resque' => $this->getResque(),
                'jobPager' => $pager,
                'jobPagerView' => $pagerView,
                'jobPagerGenerator' => $routeGenerator,
            )
        );
    }

    public function showQueueAction($queue)
    {
        list($start, $count, $showingAll) = $this->getShowParameters();

        $queue = $this->getResque()->getQueue($queue);
        $jobs = $queue->getJobs($start, $count);

        if (!$showingAll) {
            $jobs = array_reverse($jobs);
        }

        return $this->render(
            'BCCResqueBundle:Default:queue_show.html.twig',
            array(
                'queue' => $queue,
                'jobs' => $jobs,
                'showingAll' => $showingAll
            )
        );
    }

    public function listFailedAction()
    {
        list($start, $count, $showingAll) = $this->getShowParameters();

        $jobs = $this->getResque()->getFailedJobs($start, $count);

        if (!$showingAll) {
            $jobs = array_reverse($jobs);
        }

        return $this->render(
            'BCCResqueBundle:Default:failed_list.html.twig',
            array(
                'jobs' => $jobs,
                'showingAll' => $showingAll,
            )
        );
    }

    public function showJobAction($jobId)
    {
        $job = $this->getResque()->getJob($jobId);

        return $this->render(
            'BCCResqueBundle:Default:job.html.twig',
            array(
                'job' => $job,
            )
        );
    }


    public function listScheduledAction()
    {
        return $this->render(
            'BCCResqueBundle:Default:scheduled_list.html.twig',
            array(
                'timestamps' => $this->getResque()->getDelayedJobTimestamps()
            )
        );
    }

    public function showTimestampAction($timestamp)
    {
        $jobs = array();

        // we don't want to enable the twig debug extension for this...
        foreach ($this->getResque()->getJobsForTimestamp($timestamp) as $job) {
            $jobs[] = print_r($job, true);
        }

        return $this->render(
            'BCCResqueBundle:Default:scheduled_timestamp.html.twig',
            array(
                'timestamp' => $timestamp,
                'jobs' => $jobs
            )
        );
    }

    /**
     * @return \BCC\ResqueBundle\Resque
     */
    protected function getResque()
    {
        return $this->get('bcc_resque.resque');
    }

    /**
     * decide which parts of a job queue to show
     *
     * @return array
     */
    private function getShowParameters()
    {
        $showingAll = false;
        $start = -100;
        $count = -1;

        if ($this->getRequest()->query->has('all')) {
            $start = 0;
            $count = -1;
            $showingAll = true;
        }

        return array($start, $count, $showingAll);
    }


    /** @return \Doctrine\ORM\EntityManager */
    private function getEm()
    {
        return $this->registry->getManagerForClass('BCCResqueBundle:ResqueJob');
    }

    /** @return \BCC\ResqueBundle\Entity\Repository\ResqueJobRepository */
    private function getRepo()
    {
        return $this->getEm()->getRepository('BCCResqueBundle:ResqueJob');
    }
}
