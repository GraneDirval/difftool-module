<?php
/**
 * Created by PhpStorm.
 * User: Администратор
 * Date: 24.11.2018
 * Time: 11:19
 */

namespace Playwing\DiffToolBundle\DiffTool\DTO;


class RelationChangeSet
{
    /**
     * @var string
     */
    private $table;
    /**
     * @var string
     */
    private $fieldName;
    /**
     * @var string
     */
    private $oldValue;
    /**
     * @var string
     */
    private $newValue;
    /**
     * @var string
     */
    private $uuid;
    /**
     * @var string
     */
    private $referencedTableName;
    /**
     * @var string
     */
    private $referencedColumnName;

    /**
     * RelationChangeSet constructor.
     * @param string $table
     * @param string $fieldName
     * @param string $oldValue
     * @param string $newValue
     * @param string $uuid
     * @param string $referencedTableName
     * @param string $referencedColumnName
     */
    public function __construct(
        string $table,
        string $fieldName,
        string $oldValue,
        string $newValue,
        string $uuid,
        string $referencedTableName,
        string $referencedColumnName
    )
    {

        $this->table                = $table;
        $this->fieldName            = $fieldName;
        $this->oldValue             = $oldValue;
        $this->newValue             = $newValue;
        $this->uuid                 = $uuid;
        $this->referencedTableName  = $referencedTableName;
        $this->referencedColumnName = $referencedColumnName;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * @return string
     */
    public function getOldValue(): string
    {
        return $this->oldValue;
    }

    /**
     * @return string
     */
    public function getNewValue(): string
    {
        return $this->newValue;
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @return string
     */
    public function getReferencedTableName(): string
    {
        return $this->referencedTableName;
    }

    /**
     * @return string
     */
    public function getReferencedColumnName(): string
    {
        return $this->referencedColumnName;
    }


}