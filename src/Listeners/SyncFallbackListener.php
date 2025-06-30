<?php

namespace VarsuiteCore\Listeners;

use Illuminate\Foundation\Http\Events\RequestHandled;
use VarsuiteCore\CoreApi;
use VarsuiteCore\LaravelEnvironment;

/**
 * A fallback if we haven't synced with Core in at least 10 minutes.
 */
class SyncFallbackListener
{
    private CoreApi $api;
    private LaravelEnvironment $environment;

    public function __construct(CoreApi $api, LaravelEnvironment $environment)
    {
        $this->api = $api;
        $this->environment = $environment;
    }

    public function handle(RequestHandled $event): void
    {
        // Check if it's been a while since we did a sync, if so let's sync
        if (cache()->has('vscore.synced') && cache()->get('vscore.synced') < now()->subMinutes(10)->timestamp) {
            $this->api->syncData($this->environment->toArray());
            cache()->put('vscore.synced', now()->timestamp);
        }
    }
}
