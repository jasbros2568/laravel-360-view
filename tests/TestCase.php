<?php

namespace jasbros2568\View360\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use jasbros2568\View360\View360ServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [View360ServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['View360' => \jasbros2568\View360\Facades\View360::class];
    }
}
