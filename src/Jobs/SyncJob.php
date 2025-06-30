<?php

namespace VarsuiteCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use VarsuiteCore\CoreApi;
use VarsuiteCore\LaravelEnvironment;

/**
 * Handles syncing data up to Core.
 */
class SyncJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
    }

    public function handle(CoreApi $api, LaravelEnvironment $environment): void
    {
        $api->syncData($environment->toArray());

        cache()->put('vscore.synced', now()->timestamp);
    }

    public function failed(): void
    {
        $this->delete(); // No point storing failed jobs
    }
}
