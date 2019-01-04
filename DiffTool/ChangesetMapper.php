<?php
/**
 * Created by PhpStorm.
 * User: Администратор
 * Date: 22.11.2018
 * Time: 20:54
 */

namespace Playwing\DiffToolBundle\DiffTool;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Playwing\DiffToolBundle\DiffTool\DTO\PropertyChangeSet;
use Playwing\DiffToolBundle\DiffTool\DTO\RelationChangeSet;

class ChangesetMapper
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * ChangesetMapper constructor.
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }


    /**
     * @param $finalDiff
     * @return PropertyChangeSet[]
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     * @throws \Doctrine\ORM\Mapping\MappingException
     * @throws \ReflectionException
     */
    public function prepareChangesets($finalDiff): array
    {
        $preparedChangesets = [];

        foreach ($finalDiff as $tableName => $values) {

            $classMetadataFactory = $this->entityManager->getMetadataFactory();
            /** @var ClassMetadata $baseEntityMetadata */
            $baseEntityMetadata = $classMetadataFactory->getMetadataFor($values['entityName']);


            /** @var \Playwing\DiffToolBundle\DiffTool\DTO\PropertyChangeSet[] $preparedChangesets */
            foreach ($values['diffs'] as $uuid => $properties) {
                foreach ($properties as $propertyName => $changeSet) {
                    if ($baseEntityMetadata->isSingleValuedAssociation($propertyName)) {
                        $assocChangeSets    = $this->mapChangeSetForSingleValuedAssoc($baseEntityMetadata, $propertyName, $changeSet, $uuid);
                        $preparedChangesets = array_merge($preparedChangesets, array_values($assocChangeSets));
                    } else if ($baseEntityMetadata->isCollectionValuedAssociation($propertyName)) {
                        echo "-- Skipping changes for ${values['entityName']}::\${$propertyName} association - many-to-many is not supported yet.\n";

                    } else {
                        $preparedChangesets[] = new PropertyChangeSet(
                            $tableName,
                            $baseEntityMetadata->getColumnName($propertyName),
                            $changeSet[0],
                            $changeSet[1],
                            $uuid,
                            $values['entityName']
                        );
                    }
                }
            }


        }
        return $preparedChangesets;
    }

    /**
     * @param ClassMetadata $baseEntityMetadata
     * @param string        $propertyName
     * @param array         $changeSet
     * @return \Playwing\DiffToolBundle\DiffTool\DTO\PropertyChangeSet[]
     * @throws \Doctrine\ORM\Mapping\MappingException
     * @throws \ReflectionException
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     */
    private function mapChangeSetForSingleValuedAssoc(ClassMetadata $baseEntityMetadata, string $propertyName, array $changeSet, string $baseEntityUuid): array
    {
        $classMetadataFactory   = $this->entityManager->getMetadataFactory();
        $associationEntityClass = $baseEntityMetadata->getAssociationTargetClass($propertyName);
        /** @var ClassMetadata $associationEntityMeta */
        $associationEntityMeta = $classMetadataFactory->getMetadataFor($associationEntityClass);
        $associationData       = $baseEntityMetadata->getAssociationMapping($propertyName);

        $newChangeSets = [];
        foreach ($associationData['joinColumns'] as $joinColumn) {

            $joinColumnFieldName = $joinColumn['name'];
            $newChangeSets       = [
                $joinColumnFieldName => new RelationChangeSet(
                    $baseEntityMetadata->getTableName(),
                    $joinColumnFieldName,
                    $changeSet['uuid'][0],
                    $changeSet['uuid'][1],
                    $baseEntityUuid,
                    $associationEntityMeta->getTableName(),
                    $joinColumn['referencedColumnName']
                )
            ];
        }

        return $newChangeSets;
    }

}