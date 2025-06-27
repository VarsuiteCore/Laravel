<?php

namespace VarsuiteCore\Console\Commands;

use Illuminate\Console\Command;
use VarsuiteCore\Jobs\SyncJob;

class TestCommand extends Command
{
    protected $signature = 'vscore:test';

    protected $description = 'Tests if the site is connected to Varsuite Core';

    public function handle(): void
    {
        SyncJob::dispatchSync();
    }
}
