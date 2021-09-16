#!/usr/bin/env php
<?php declare(strict_types = 1);

use App\BuilderCommand;
use Symfony\Component\Console\Application;

require __DIR__ . '/vendor/autoload.php';

$command = new BuilderCommand();
$application = new Application('Application builder', 'dev-master');
$application->add($command);
$application->setDefaultCommand($command->getName());
$application->run();
