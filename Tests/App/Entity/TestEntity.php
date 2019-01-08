<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 08.01.19
 * Time: 13:27
 */

namespace Playwing\DiffToolBundle\Tests\App\Entity;


use Doctrine\ORM\Mapping as ORM;
use Playwing\DiffToolBundle\Entity\Interfaces\HasUuid;


/**
 * @ORM\Entity
 * @ORM\Table(name="test_entity")
 */
class TestEntity implements HasUuid
{

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $uuid;

    /**
     * TestEntity constructor.
     * @param $id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }


    /**
     * @param string $uuid
     */
    public function setUuid(string $uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }
}