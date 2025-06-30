<?php

namespace VarsuiteCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use VarsuiteCore\Jobs\InitiateBackupJob;

class BackupController
{
    public function store(string $siteId, string $backupId): JsonResponse
    {
        if (config('queue.default') === 'sync') {
            InitiateBackupJob::dispatchSync($siteId, $backupId);
        } else {
            InitiateBackupJob::dispatch($siteId, $backupId);
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
