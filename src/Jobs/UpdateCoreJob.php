<?php

namespace VarsuiteCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Symfony\Component\Process\Process;

class UpdateCoreJob extends CoreJob
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        $this->logger()->debug('Updating Laravel core');

        $identifiers = [
            'laravel/*',
            'illuminate/*',
        ];

        $process = Process::fromShellCommandline(
            'composer update ' . implode(' ', $identifiers) . ' --optimize-autoloader --with-all-dependencies --no-interaction --no-progress'
        );
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(120);
        $process->mustRun();
    }
}