<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 19.11.18
 * Time: 18:38
 */

namespace Playwing\DiffToolBundle\DiffTool;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;

class EntityDataProvider
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;


    /**
     * EntityDataProvider constructor.
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getEntityData(string $entityClass, array $ignoredFields = [])
    {
        /** @var ClassMetadata $metadata */
        $classMetadataFactory = $this->entityManager->getMetadataFactory();
        $metadata             = $classMetadataFactory->getMetadataFor($entityClass);

        $propertiesToSelect = $this->getPropertiesToSelect($entityClass);
        $propertyString     = implode(',', $propertiesToSelect);

        $associationNames = array_filter($metadata->getAssociationNames(), function ($name) use ($metadata) {
            $associationType = $metadata->getAssociationMapping($name);

            if ($associationType['type'] == ClassMetadata::MANY_TO_MANY) {
                return true;
            }

            return !$metadata->isAssociationInverseSide($name);
        });
        if (count($ignoredFields)) {
            $associationNames = array_diff($associationNames, $ignoredFields);
        }

        $builder = $this
            ->entityManager
            ->createQueryBuilder()
            ->from($entityClass, 'c')
            ->select("partial c.{" . $propertyString . "}");

        foreach ($associationNames as $name) {
            $associationEntity           = $metadata->getAssociationTargetClass($name);
            $associationProperties       = $this->getPropertiesToSelect($associationEntity);
            $associationPropertiesString = implode(',', $associationProperties);

            $builder->addSelect("partial $name.{" . $associationPropertiesString . "}");
            $builder->leftJoin("c.$name", "$name");
        }

        $query = $builder->getQuery();
        $query->setHint(Query::HINT_FORCE_PARTIAL_LOAD, true);

        return $query->getResult();

    }

    /**
     * @param $entity
     * @return array
     */
    private function getPropertiesToSelect($entity): array
    {
        $metadata           = $this->entityManager->getClassMetadata($entity);
        $diffToolEntityData = EntitySerializationDataProvider::$list[$entity] ?? null;

        $fieldsToSelect = $metadata->getFieldNames();
        if ($diffToolEntityData && isset($diffToolEntityData[1])) {
            $fieldsToSelect = array_diff($fieldsToSelect, $diffToolEntityData[1]);
        }
        return $fieldsToSelect;

    }
}