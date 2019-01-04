<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 21.11.18
 * Time: 13:51
 */

namespace Playwing\DiffToolBundle\DiffTool\DTO;


class PropertyChangeSet
{
    private $fieldName;

    private $oldValue;

    private $newValue;
    private $table;
    private $uuid;
    private $entityName;

    /**
     * PropertyChangeSet constructor.
     * @param      $table
     * @param      $fieldName
     * @param      $oldValue
     * @param      $newValue
     * @param      $uuid
     * @param      $entityName
     */
    public function __construct(
        $table,
        $fieldName,
        $oldValue,
        $newValue,
        $uuid,
        $entityName
    )
    {
        $this->fieldName  = $fieldName;
        $this->oldValue   = $oldValue;
        $this->newValue   = $newValue;
        $this->table      = $table;
        $this->uuid       = $uuid;
        $this->entityName = $entityName;
    }

    /**
     * @return mixed
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * @return mixed
     */
    public function getOldValue()
    {
        return $this->oldValue;
    }

    /**
     * @return mixed
     */
    public function getNewValue()
    {
        return $this->newValue;
    }

    /**
     * @return mixed
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @return mixed
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @return mixed
     */
    public function getEntityName()
    {
        return $this->entityName;
    }


}