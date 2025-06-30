<?php

namespace VarsuiteCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use VarsuiteCore\Jobs\SyncJob;

class UserController
{
    public function store(Request $request): JsonResponse
    {
        $model = config('vscore.user_model');
        $model::create([
            'name' => $request->string('display_name'),
            'email' => $request->string('email'),
            'password' => Hash::make($request->string('password')),
        ]);
        SyncJob::dispatchSync();

        return response()->json([
            'success' => true,
        ]);
    }

    public function update(Request $request, string $userId): JsonResponse
    {
        $model = config('vscore.user_model');
        $model::findOrFail($userId)->update([
            'name' => $request->string('display_name'),
            'email' => $request->string('email'),
        ]);
        SyncJob::dispatchSync();

        return response()->json([
            'success' => true,
        ]);
    }

    public function delete(string $userId): JsonResponse
    {
        $model = config('vscore.user_model');
        $model::findOrFail($userId)->delete();
        SyncJob::dispatchSync();

        return response()->json([
            'success' => true,
        ]);
    }
}
