<?php

namespace VarsuiteCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SSOController
{
    /**
     * Generate a one-time use token for SSO authentication.
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $request->string('userId');
        abort_if(null === $userId, 400, 'No user ID provided');

        $tokens = cache()->get('vscore.sso_tokens');
        if (!\is_array($tokens)) {
            $tokens = [];
        }
        $token = bin2hex(random_bytes(40));
        $tokens[$token] = ['token' => $token, 'userId' => $userId->toString()];
        cache()->put('vscore.sso_tokens', $tokens);

        return response()->json([
            'success' => true,
            'debug' => $request->all(),
            'data' => [
                'token' => $token,
            ]
        ]);
    }

    /**
     * Login using one-time SSO token.
     */
    public function show(Request $request): RedirectResponse
    {
        $token = $request->input('token');
        $tokens = cache()->get('vscore.sso_tokens');
        if (!\is_array($tokens)) {
            $tokens = [];
        }
        $ssoData = $tokens[$token] ?? null;

        // Revoke token, since we've now used it
        unset($tokens[$token]);
        cache()->put('vscore.sso_tokens', $tokens);

        // Log the user in
        auth(config('auth.defaults.guard'))->loginUsingId($ssoData['userId']);

        // Redirect to site
        return redirect()->to('/');
    }
}
