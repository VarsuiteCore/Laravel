<?php

namespace VarsuiteCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use VarsuiteCore\Jobs\SyncJob;
use VarsuiteCore\Jobs\UpdateCoreJob;

class CoreController
{
    public function update(): JsonResponse
    {
        UpdateCoreJob::dispatchSync();
        SyncJob::dispatchSync();

        return response()->json([
            'success' => true,
        ]);
    }
}
