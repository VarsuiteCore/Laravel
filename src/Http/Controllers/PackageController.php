<?php

namespace VarsuiteCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use VarsuiteCore\Jobs\DeletePackagesJob;
use VarsuiteCore\Jobs\SyncJob;
use VarsuiteCore\Jobs\UpdatePackagesJob;

class PackageController
{
    public function update(Request $request): JsonResponse
    {
        $identifiers = $request->array('identifiers');
        abort_if(null === $identifiers, 400, 'No identifiers provided');

        UpdatePackagesJob::dispatchSync($identifiers);
        SyncJob::dispatchSync();

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $identifiers = $request->array('identifiers');
        abort_if(null === $identifiers, 400, 'No identifiers provided');

        DeletePackagesJob::dispatchSync($identifiers);
        SyncJob::dispatchSync();

        return response()->json([
            'success' => true,
        ]);
    }
}
