<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 08.01.19
 * Time: 12:53
 */

namespace Playwing\DiffToolBundle\Tests\App;


use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use Liip\FunctionalTestBundle\LiipFunctionalTestBundle;
use Playwing\DiffToolBundle\DiffToolBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            new FrameworkBundle(),
            new DoctrineBundle(),
            new DiffToolBundle(),

            new LiipFunctionalTestBundle(),
            new DoctrineFixturesBundle()
        );

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        // We don't need that Environment stuff, just one config
        $loader->load(__DIR__.'/config.yml');
    }


}