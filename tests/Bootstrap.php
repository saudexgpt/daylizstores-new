<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use PHPUnit\Event\TestRunner\Finished;
use PHPUnit\Event\TestRunner\FinishedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/*
|--------------------------------------------------------------------------
| Bootstrap The Test Environment
|--------------------------------------------------------------------------
|
| You may specify console commands that execute once before your test is
| run. You are free to add your own additional commands or logic into
| this file as needed in order to help your test suite run quicker.
|
*/
class Bootstrap implements Extension
{
    use CreatesApplication;

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $console = $this->createApplication()->make(Kernel::class);

        foreach (['config:cache', 'event:cache'] as $command) {
            $console->call($command);
        }

        $facade->registerSubscriber(new class implements FinishedSubscriber {
            public function notify(Finished $event): void
            {
                array_map('unlink', glob('bootstrap/cache/*.phpunit.php'));
            }
        });
    }
}
