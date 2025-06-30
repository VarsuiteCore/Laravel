<?php

namespace VarsuiteCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Symfony\Component\Process\Process;

class DeletePackagesJob extends CoreJob
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public array $identifiers)
    {
    }

    public function handle(): void
    {
        $this->logger()->debug('Deleting packages: ' . implode(', ', $this->identifiers));

        $process = Process::fromShellCommandline(
            'composer remove ' . implode(' ', $this->identifiers) . ' --optimize-autoloader --no-interaction --no-progress'
        );
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(120);
        $process->mustRun();
    }
}
