<?php

/**
 * Varsuite Core configuration file
 *
 * @link https://vs-core.com
 */

return [
    /**
     * Your unique site key.
     * This is a secret, do not commit or share anywhere!
     */
    'key' => env('VSCORE_KEY'),

    /**
     * Your app's user model.
     * We use this to create / update / delete users via Core.
     */
    'user_model' => \App\Models\User::class,
];