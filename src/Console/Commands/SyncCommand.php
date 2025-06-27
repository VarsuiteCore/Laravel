<?php

namespace VarsuiteCore\Console\Commands;

use Illuminate\Console\Command;
use VarsuiteCore\Jobs\SyncJob;

class SyncCommand extends Command
{
    protected $signature = 'vscore:sync';

    protected $description = 'Triggers a sync manually with Varsuite Core';

    public function handle(): void
    {
        if (config('queue.default') === 'sync') {
            // Run immediately since this app has no queue setup
            SyncJob::dispatchSync();
        } else {
            // Add to normal queue
            SyncJob::dispatch();
        }
    }
}
