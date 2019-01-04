<?php
/**
 * Created by PhpStorm.
 * User: Администратор
 * Date: 22.11.2018
 * Time: 20:52
 */

namespace Playwing\DiffToolBundle\DiffTool;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Playwing\DiffToolBundle\Entity\Interfaces\HasUuid;
use Playwing\DiffToolBundle\Utils\FixtureDataLoader;

class DataComparator
{
    /**
     * @var EntityManager
     */
    private $entityManager;
    /**
     * @var EntityDataProvider
     */
    private $entityDataProvider;
    /**
     * @var EntitySerializer
     */
    private $entitySerializer;

    private $paths = [];
    /**
     * @var FixtureDataLocator
     */
    private $locator;

    /**
     * DataComparator constructor.
     * @param EntityManager      $entityManager
     * @param EntityDataProvider $entityDataProvider
     * @param EntitySerializer   $entitySerializer
     * @param FixtureDataLocator $locator
     */
    public function __construct(
        EntityManager $entityManager,
        EntityDataProvider $entityDataProvider,
        EntitySerializer $entitySerializer,
        FixtureDataLocator $locator
    )
    {
        $this->entityManager      = $entityManager;
        $this->entityDataProvider = $entityDataProvider;
        $this->entitySerializer   = $entitySerializer;
        $this->locator            = $locator;
    }

    public function compareDiffBetweenTwoFixtures(array $newFixture, array $oldFixture)
    {
        $indexedOldFixtures = [];
        foreach ($oldFixture as $oldFixtureEntry) {
            $indexedOldFixtures[$oldFixtureEntry['uuid']] = $oldFixtureEntry;
        }
        $indexedNewFixtures = [];
        foreach ($newFixture as $newFixtureEntry) {
            $indexedNewFixtures[$newFixtureEntry['uuid']] = $newFixtureEntry;
        }

        $deletedEntries = [];
        $addedEntries   = [];
        $diffs          = [];
        foreach ($indexedOldFixtures as $uuid => $indexedOldFixture) {
            if (!isset($indexedNewFixtures[$uuid])) {
                $deletedEntries[$uuid] = $indexedOldFixture;
            }
        }

        foreach ($indexedNewFixtures as $uuid => $indexedNewFixture) {
            if (!isset($indexedOldFixtures[$uuid])) {
                $addedEntries[$uuid] = $indexedNewFixture;
            } else {
                $diff = $this->arrayDiffRecursive($indexedNewFixture, $indexedOldFixtures[$uuid]);
                if (!empty($diff)) {
                    $diffs[$uuid] = $diff;
                }

            }

        }


        return [$diffs, $addedEntries, $deletedEntries];
    }

    /**
     * @param array  $mapping
     * @param string $entity
     * @return array
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     * @throws \ReflectionException
     */
    public function compareDiffBetweenFixturesAndDB(array $mapping, string $entity): array
    {
        $interfaces = class_implements($entity);
        if (!isset($interfaces[HasUuid::class])) {
            throw new \Exception(sprintf('`%s` should implement `%s` interface', $entity, HasUuid::class));
        }

        $differences   = [];
        $addedToDB     = [];
        $removedFromDB = [];

        $ignoredFields = $mapping[1] ?? [];
        list($indexedActualData, $indexedFixtureData) = $this->getSerializedEntityData($entity, $mapping[0], $ignoredFields);

        $diffs                = [];
        $classMetadataFactory = $this->entityManager->getMetadataFactory();
        /** @var ClassMetadata $metadata */
        $metadata = $classMetadataFactory->getMetadataFor($entity);

        foreach ($indexedFixtureData as $fixtureRow) {
            if (!isset($indexedActualData[$fixtureRow['uuid']])) {
                $removedFromDB[$fixtureRow['uuid']] = ['row' => $fixtureRow, 'table' => $metadata->getTableName()];
            }
        }

        foreach ($indexedActualData as $actualRow) {

            if (!isset($indexedFixtureData[$actualRow['uuid']])) {
                $addedToDB[$actualRow['uuid']] = ['row' => $actualRow, 'table' => $metadata->getTableName()];
                continue;
            }
            $fixtureRow = $indexedFixtureData[$actualRow['uuid']];

            $diff = $this->arrayDiffRecursive($actualRow, $fixtureRow);

            foreach ($ignoredFields as $field) {
                unset($diff[$field]);
            }

            unset($diff['id']);

            if (!empty($diff)) {
                $diffs[$actualRow['uuid']] = $diff;
            }

        }

        if (count($diffs)) {
            $differences = [
                'diffs'      => $diffs,
                'entityName' => $entity,
                'tableName'  => $metadata->getTableName()
            ];
        }
        return [$differences, $addedToDB, $removedFromDB];
    }


    /**
     * @param string $entity
     * @param string $fileName
     * @param array  $ignoredFields
     * @return array
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     * @throws \ReflectionException
     */
    private function getSerializedEntityData(string $entity, string $fileName, array $ignoredFields): array
    {
        /** @var HasUuid[] $actualData */
        $actualData = $this->entityDataProvider->getEntityData($entity, $ignoredFields);
        $path        = $this->locator->locateFile($fileName);
        $fixtureData = json_decode(file_get_contents($path), true);

        $classMetadataFactory = $this->entityManager->getMetadataFactory();
        /** @var ClassMetadata $metadata */
        $metadata = $classMetadataFactory->getMetadataFor($entity);


        $indexedActualData = [];
        foreach ($actualData as $row) {
            $indexedActualData[$row->getUuid()] = $this->entitySerializer->serializeEntity($metadata, $row, $classMetadataFactory);
        }

        $indexedFixtureData = [];
        foreach ($fixtureData as $row) {
            $indexedFixtureData[$row['uuid']] = $row;
        }

        return array($indexedActualData, $indexedFixtureData);
    }

    private function arrayDiffRecursive($array1, $array2)
    {
        foreach ($array1 as $key => $value) {

            if (is_array($value)) {
                if (!isset($array2[$key])) {
                    $difference[$key] = [null, $value];
                } elseif (!is_array($array2[$key])) {
                    $difference[$key] = [$array2[$key], $value];
                } else {
                    $new_diff = $this->arrayDiffRecursive($value, $array2[$key]);
                    if ($new_diff != FALSE) {
                        $difference[$key] = $new_diff;
                    }
                }
            } elseif ((!isset($array2[$key]) || @$array2[$key] != $value) && !(@$array2[$key] === null && $value === null)) {
                $difference[$key] = [@$array2[$key], $value];
            }
        }
        return !isset($difference) ? 0 : $difference;
    }

}