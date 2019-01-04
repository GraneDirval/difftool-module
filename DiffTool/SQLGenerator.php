<?php
/**
 * Created by PhpStorm.
 * User: Администратор
 * Date: 22.11.2018
 * Time: 20:50
 */

namespace Playwing\DiffToolBundle\DiffTool;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Playwing\DiffToolBundle\DiffTool\DTO\PropertyChangeSet;
use Playwing\DiffToolBundle\DiffTool\DTO\RelationChangeSet;

class SQLGenerator
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;


    /**
     * SQLGenerator constructor.
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function createDeleteSQL(string $uuid, array $data)
    {
        return sprintf("DELETE FROM %s where `uuid` = %s", $data['table'], $this->convertToMysqlColumnValue($uuid));
    }

    /**
     * @param \Playwing\DiffToolBundle\DiffTool\DTO\PropertyChangeSet[] $preparedChangesets
     * @return array
     */
    public function createUpdateSQLs(array $preparedChangesets): array
    {
        $ups               = [];
        $downs             = [];
        $indexedChangesets = [];

        foreach ($preparedChangesets as $changeSet) {
            $indexedChangesets[$changeSet->getUuid()][] = $changeSet;
        }

        /** @var PropertyChangeSet[]|RelationChangeSet[] $changeSets */
        foreach ($indexedChangesets as $uuid => $changeSets) {

            if (!$changeSets) {
                continue;
            }

            $propertyChangesets = array_filter($changeSets, function ($changeSet) {
                return $changeSet instanceof PropertyChangeSet;
            });
            if ($propertyChangesets) {
                list($up, $down) = $this->createSQLForChangesets($changeSets);
                $ups[]   = $up;
                $downs[] = $down;
            }

            foreach ($changeSets as $changeSet) {
                if (!$changeSet instanceof RelationChangeSet) {
                    continue;
                }
                list($upSQL, $downSQL) = $this->createSQLForRelationChangeset($changeSet);

                $ups[]   = $upSQL;
                $downs[] = $downSQL;

            }
        }
        return array($ups, $downs);
    }

    /**
     * @param $changeSet
     * @return array
     */
    private function createSQLForRelationChangeset(RelationChangeSet $changeSet): array
    {
        $sql = "UPDATE {{table}} JOIN `{{refTable}}` ON `{{refTable}}`.`uuid` = '{{refTableUuid}}' SET `{{table}}`.`{{fieldName}}` = `{{refTable}}`.`{{refColumnName}}` WHERE `{{table}}`.`uuid` = '{{baseUuid}}'";

        $params = [
            '{{table}}'         => $changeSet->getTable(),
            '{{fieldName}}'     => $changeSet->getFieldName(),
            '{{refTable}}'      => $changeSet->getReferencedTableName(),
            '{{refColumnName}}' => $changeSet->getReferencedColumnName(),
            '{{refTableUuid}}'  => $changeSet->getNewValue(),
            '{{baseUuid}}'      => $changeSet->getUuid(),
        ];
        $upSql  = $this->compileStr($params, $sql);


        $params  = [
            '{{table}}'         => $changeSet->getTable(),
            '{{fieldName}}'     => $changeSet->getFieldName(),
            '{{refTable}}'      => $changeSet->getReferencedTableName(),
            '{{refColumnName}}' => $changeSet->getReferencedColumnName(),
            '{{refTableUuid}}'  => $changeSet->getOldValue(),
            '{{baseUuid}}'      => $changeSet->getUuid(),
        ];
        $downSql = $this->compileStr($params, $sql);

        return [$upSql, $downSql];
    }

    /**
     * @param $changeSets
     * @return array
     */
    private function createSQLForChangesets(array $changeSets): array
    {
        $upSQLUpdateParts   = [];
        $downSQLUpdateParts = [];


        /** @var \Playwing\DiffToolBundle\DiffTool\DTO\PropertyChangeSet[] $changeSets */
        foreach ($changeSets as $changeSet) {


            $newValue = $this->convertToMysqlColumnValue($changeSet->getNewValue());
            $oldValue = $this->convertToMysqlColumnValue($changeSet->getOldValue());

            $upSQLUpdateParts[]   = sprintf("`%s` = %s", $changeSet->getFieldName(), $newValue);
            $downSQLUpdateParts[] = sprintf("`%s` = %s", $changeSet->getFieldName(), $oldValue);
        }

        $up = null;
        if ($upSQLUpdateParts && isset($changeSet)) {
            $up = sprintf(
                "UPDATE %s SET %s WHERE `%s`.`uuid` = %s",
                $changeSet->getTable(),
                implode(',', $upSQLUpdateParts),
                $changeSet->getTable(),
                $this->convertToMysqlColumnValue($changeSet->getUuid())
            );
        }

        $down = null;
        if ($downSQLUpdateParts && isset($changeSet)) {
            $down = sprintf(
                "UPDATE %s SET %s WHERE `%s`.`uuid` = %s",
                $changeSet->getTable(),
                implode(',', $downSQLUpdateParts),
                $changeSet->getTable(),
                $this->convertToMysqlColumnValue($changeSet->getUuid())
            );
        }
        return [$up, $down];
    }

    public function createInsertSQL(string $uuid, array $data, ClassMetadata $metadata)
    {

        unset($data['row']['id']);

        $propertyFields    = [];
        $associationFields = [];

        foreach ($data['row'] as $property => $value) {
            if ($metadata->isSingleValuedAssociation($property)) {
                $associationMapping   = $metadata->getAssociationMapping($property);
                $associationMetadata  = $this->entityManager->getClassMetadata($associationMapping['targetEntity']);
                $associationFieldData = [
                    'joinColumns'   => [],
                    'joinValue'     => $value['uuid'],
                    'joinTableName' => $associationMetadata->getTableName(),
                ];

                foreach ($associationMapping['joinColumns'] as $joinColumn) {
                    $associationFieldData['joinColumns'][] = $joinColumn;
                }

                $associationFields[$property] = $associationFieldData;
            } else if ($metadata->isCollectionValuedAssociation($property)) {
                // NOT implemented yet.
            } else {


                $propertyFields[$property] = $value;
            }

        }


        $propertyFieldNames = array_keys($propertyFields);
        $propertyFieldNames = array_map(function ($val) use ($metadata) {
            $fieldName = $metadata->getColumnName($val);
            return "`$fieldName`";
        }, $propertyFieldNames);

        $propertyFieldValues = array_values($propertyFields);
        $propertyFieldValues = array_map(function ($val) {

            return $this->convertToMysqlColumnValue($val);

        }, $propertyFieldValues);

        foreach ($associationFields as $fieldData) {
            foreach ($fieldData['joinColumns'] as $joinColumn) {
                $propertyFieldNames[]  = "`{$joinColumn['name']}`";
                $propertyFieldValues[] = $this->compileStr(
                    [
                        '{{joinColumn}}'          => $joinColumn['referencedColumnName'],
                        '{{referencedTableName}}' => $fieldData['joinTableName'],
                        '{{uuid}}'                => $fieldData['joinValue'],
                    ],
                    "(SELECT `{{joinColumn}}` FROM `{{referencedTableName}}` WHERE `uuid` = '{{uuid}}')"
                );
            }
        }

        if ($associationFields) {
            $sql = 'INSERT IGNORE INTO {{tableName}} ({{propertyFieldsString}}) VALUES ({{propertyValuesString}})';
        } else {
            $sql = 'INSERT INTO {{tableName}} ({{propertyFieldsString}}) VALUES ({{propertyValuesString}})';
        }


        return $this->compileStr([
            '{{tableName}}'            => $metadata->getTableName(),
            '{{propertyFieldsString}}' => implode(',', $propertyFieldNames),
            '{{propertyValuesString}}' => implode(',', $propertyFieldValues),
        ],
            $sql);
    }

    /**
     * @param $params
     * @param $str
     * @return string
     */
    private function compileStr($params, $str): string
    {


        foreach ($params as $prop => $val) {
            $str = str_replace($prop, $val, $str);
        }



        return $str;
    }

    private function convertToMysqlColumnValue($val): string
    {

        if (is_null($val)) {
            return 'NULL';
        }
        if (is_bool($val)) {
            $val = (int)$val;
        }

        $val = str_replace("'","''",$val);

        return "'$val'";
    }

}