<?php

namespace VarsuiteCore\Backup;

/**
 * Responsible for retrying a process based on given number of retries and backoff strategy.
 */
class RetryManager
{
    /** @var int How many times should we try the process? */
    private int $tries = 3;

    /** @var int Number of seconds to wait in between attempts. */
    private int $delay = 60; // 1 minute

    public function execute(\Closure $closure): mixed
    {
        $attempt = 1;

        while ($attempt <= $this->tries) {
            try {
                return $closure();
            } catch (\Throwable $e) {
                if ($attempt === $this->tries) {
                    // Was the last attempt, rethrow the exception for proper catching / logging
                    throw $e;
                }

                // Go for the next attempt after a wait
                if ($this->delay > 0) {
                    sleep($this->delay);
                }
                $attempt++;
            }
        }

        throw new \LogicException('Should never get here');
    }
}
