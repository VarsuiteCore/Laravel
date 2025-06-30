<?php

namespace VarsuiteCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Symfony\Component\Process\Process;

class UpdatePackagesJob extends CoreJob
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public array $identifiers)
    {
    }

    public function handle(): void
    {
        $this->logger()->debug('Updating packages: ' . implode(', ', $this->identifiers));

        $process = Process::fromShellCommandline(
            'composer require ' . implode(' ', $this->identifiers) . ' --optimize-autoloader --with-all-dependencies --no-interaction --no-progress'
        );
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(120);
        $process->mustRun();
    }
}