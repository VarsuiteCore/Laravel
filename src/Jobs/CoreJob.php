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
        $logger = new Logger('vscore');
        $handler = new RotatingFileHandler(storage_path('logs/vscore.log'), 7);
        $logger->pushHandler($handler);

        return $logger;
    }
}