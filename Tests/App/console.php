<?php
set_time_limit(0);

require __DIR__.'/../../vendor/autoload.php';;

use Symfony\Bundle\FrameworkBundle\Console\Application;

$kernel      = new \Playwing\DiffToolBundle\Tests\App\AppKernel('dev', false);
$application = new Application($kernel);

$application->run();
