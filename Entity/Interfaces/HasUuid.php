<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 21.11.18
 * Time: 14:11
 */

namespace Playwing\DiffToolBundle\Entity\Interfaces;


interface HasUuid
{

    /**
     * @param string $uuid
     */
    public function setUuid(string $uuid);

    /**
     * @return string
     */
    public function getUuid(): string;
}