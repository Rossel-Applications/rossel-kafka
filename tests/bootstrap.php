<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

// Register the test namespace manually since dump-autoload cannot run
$loader = new Composer\Autoload\ClassLoader();
$loader->addPsr4('Rossel\\RosselKafka\\Tests\\', __DIR__);
$loader->register();
