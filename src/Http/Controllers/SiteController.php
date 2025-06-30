<?php

namespace VarsuiteCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use VarsuiteCore\LaravelEnvironment;

class SiteController
{
    public function show(LaravelEnvironment $environment): JsonResponse
    {
        $data = $environment->toArray();
        cache()->put('vscore.synced', now()->timestamp);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
