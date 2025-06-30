<?php

namespace VarsuiteCore\Jobs;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * Common utilities shared between jobs.
 *
 * @internal
 */
abstract class CoreJob
{
    public function logger(): Logger
    {
        return new Logger(
            'core',
            [
                new RotatingFileHandler(storage_path('logs/core.log'), 7),
            ]
        );
    }
}