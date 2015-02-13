<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 2/12/15
 * Time: 4:33 PM
 */

namespace BCC\ResqueBundle\Entity\Listener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use BCC\ResqueBundle\Entity\Job;

class ManyToAnyListener {
    private $registry;
    private $ref;
    public function __construct(\Symfony\Bridge\Doctrine\RegistryInterface $registry)
    {
        $this->registry = $registry;
        $this->ref = new \ReflectionProperty('BCC\ResqueBundle\Entity\ResqueJob', 'relatedEntities');
        $this->ref->setAccessible(true);
    }
    public function postLoad(\Doctrine\ORM\Event\LifecycleEventArgs $event)
    {
        $entity = $event->getEntity();
        if ( ! $entity instanceof \BCC\ResqueBundle\Entity\ResqueJob) {
            return;
        }
        $this->ref->setValue($entity, new PersistentRelatedEntitiesCollection($this->registry, $entity));
    }
    public function preRemove(LifecycleEventArgs $event)
    {
        $entity = $event->getEntity();
        if ( ! $entity instanceof Job) {
            return;
        }
        $con = $event->getEntityManager()->getConnection();
        $con->executeUpdate("DELETE FROM resque_related_entities WHERE job_id = :id", array(
            'id' => $entity->getId(),
        ));
    }
    public function postPersist(\Doctrine\ORM\Event\LifecycleEventArgs $event)
    {
        $entity = $event->getEntity();
        if ( ! $entity instanceof \BCC\ResqueBundle\Entity\ResqueJob) {
            return;
        }
        $con = $event->getEntityManager()->getConnection();
        foreach ($this->ref->getValue($entity) as $relatedEntity) {
            $relClass = \Doctrine\Common\Util\ClassUtils::getClass($relatedEntity);
            $relId = $this->registry->getManagerForClass($relClass)->getMetadataFactory()->getMetadataFor($relClass)->getIdentifierValues($relatedEntity);
            asort($relId);
            if ( ! $relId) {
                throw new \RuntimeException('The identifier for the related entity "'.$relClass.'" was empty.');
            }
            $con->executeUpdate("INSERT INTO resque_related_entities (job_id, related_class, related_id) VALUES (:jobId, :relClass, :relId)", array(
                'jobId' => $entity->getId(),
                'relClass' => $relClass,
                'relId' => json_encode($relId),
            ));
        }
    }
    public function postGenerateSchema(\Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs $event)
    {
        $schema = $event->getSchema();
// When using multiple entity managers ignore events that are triggered by other entity managers.
        if ($event->getEntityManager()->getMetadataFactory()->isTransient('BCC\ResqueBundle\Entity\ResqueJob')) {
            return;
        }
        $table = $schema->createTable('resque_related_entities');
        $table->addColumn('job_id', 'bigint', array('nullable' => false, 'unsigned' => true));
        $table->addColumn('related_class', 'string', array('nullable' => false, 'length' => '150'));
        $table->addColumn('related_id', 'string', array('nullable' => false, 'length' => '100'));
        $table->setPrimaryKey(array('job_id', 'related_class', 'related_id'));
        $table->addForeignKeyConstraint('resque_jobs', array('job_id'), array('id'));
    }
}