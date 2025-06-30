<?php

namespace VarsuiteCore\Factories;

use Symfony\Component\Process\Process;

/**
 * Gives us a way to create a different Symfony Process instance with varying arguments, great for mocking during testing. 
 */
class ProcessFactory
{
    public function __invoke(array $args): Process
    {
        return new Process($args);
    }
}