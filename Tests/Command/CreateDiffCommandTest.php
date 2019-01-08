<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 08.01.19
 * Time: 12:59
 */

namespace Playwing\DiffToolBundle\Tests\Command;


use Liip\FunctionalTestBundle\Tests\Command\CommandTest;
use Playwing\DiffToolBundle\Command\UpdateFixturesFromV1Command;
use Playwing\DiffToolBundle\Tests\App\AppKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CreateDiffCommandTest extends \Liip\FunctionalTestBundle\Test\WebTestCase
{


    protected static function getKernelClass()
    {
        return AppKernel::class;
    }


    public function setUp()
    {
        self::bootKernel();

        $this->loadFixtures([]);
    }

    public function testName()
    {
        $result = $this->runCommand('development:compare_diff_between_db_and_fixture');

        $this->assertEquals($result->getStatusCode(), 0);
    }
}