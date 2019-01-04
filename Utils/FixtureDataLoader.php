<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 13.07.18
 * Time: 13:47
 */

namespace Playwing\DiffToolBundle\Utils;


use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;

class FixtureDataLoader
{
    public static function loadDataFromJSONFile(string $path, string $fileName)
    {
        $content = file_get_contents($path . $fileName);
        return json_decode($content, true);
    }

    public static function insertRow(array $row, string $className, EntityManagerInterface $entityManager)
    {

        /** @var Connection $connection */
        $connection = $entityManager->getConnection();
        /** @var ClassMetadataFactory $metadataFactory */
        $metadataFactory = $entityManager->getMetadataFactory();
        /** @var ClassMetadata $metadata */
        $metadata = $metadataFactory->getMetadataFor($className);

        $connection->insert($metadata->getTableName(), $row);

    }
}