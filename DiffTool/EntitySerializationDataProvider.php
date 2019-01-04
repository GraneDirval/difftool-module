<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 20.11.18
 * Time: 16:51
 */

namespace Playwing\DiffToolBundle\DiffTool;


class EntitySerializationDataProvider
{
    private $list = [];


    /**
     * EntitySerializationDataProvider constructor.
     * @param array $list
     */
    public function __construct(array $list = [])
    {
        $this->list = $list;
    }


    public function getEntityMappings(string $entityName = null): array
    {
        if ($entityName) {
            return array_filter($this->list, function ($key) use ($entityName) {
                return mb_strpos($key, $entityName);
            }, ARRAY_FILTER_USE_KEY);
        }
        return $this->list;
    }
}