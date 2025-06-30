<?php

namespace VarsuiteCore\Tests;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            \VarsuiteCore\VarsuiteCoreServiceProvider::class,
        ];
    }
}