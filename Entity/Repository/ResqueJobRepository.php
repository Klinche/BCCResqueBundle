<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 2/12/15
 * Time: 4:15 PM
 */

namespace BCC\ResqueBundle\Entity\Repository;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use DateTime;
use Doctrine\DBAL\Connection;


class ResqueJobRepository extends EntityRepository {

    private $dispatcher;
    private $registry;

    /**
     * @DI\InjectParams({
     * "dispatcher" = @DI\Inject("event_dispatcher"),
     * })
     * @param EventDispatcherInterface $dispatcher
     */
    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }
    /**
     * @DI\InjectParams({
     * "registry" = @DI\Inject("doctrine"),
     * })
     * @param RegistryInterface $registry
     */
    public function setRegistry(RegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param $uuid
     * @return ResqueJob|null
     */
    public function findOneByResqueUUID($uuid)
    {
        return $this->_em->createQuery("SELECT j FROM BCCResqueBundle:ResqueJob j WHERE j.resqueUUID = :resqueUUID")
            ->setParameter('resqueUUID', $uuid)
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

}