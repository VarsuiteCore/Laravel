<?php

namespace VarsuiteCore;

use Illuminate\Support\Facades\Http;
use VarsuiteCore\Exceptions\CannotConnectToCoreException;
use VarsuiteCore\Exceptions\CoreCannotConnectException;
use VarsuiteCore\Exceptions\InvalidSiteKeyException;

/**
 * Handles API communication between site and Core.
 */
class CoreApi
{
    private const DEBUG = true;

    private string $testUrl = 'https://core.test/api';

    private string $prodUrl = 'https://app.vs-core.com/api';

    public function syncData(array $data): void
    {
        $this->send('POST', 'data', $data);
    }

    private function send(string $method, string $url, ?array $data = null): \stdClass
    {
        $method = strtoupper($method);
        $key = config('vscore.key');
        if (null === $key) {
            throw new InvalidSiteKeyException('No site key has been set up yet.');
        }
        $url = (self::DEBUG ? $this->testUrl : $this->prodUrl) . '/' . $url;

        // Generate signature
        $body = isset($data) ? json_encode($data) : '';
        $signature = hash_hmac('sha256', $body, $key);

        // Send request
        $response = Http::acceptJson()
            ->acceptJson()
            ->asJson()
            ->withToken($key)
            ->withHeader('Signature', $signature)
            ->when(self::DEBUG, function ($request) {
                $request
                    ->dontTruncateExceptions() // Full exceptions for debugging
                    ->withoutVerifying(); // Disable SSL verification only in debug mode
            })
            ->timeout(10)
            ->send($method, $url, ['body' => $body]);

        if ($response->requestTimeout()) {
            throw new CannotConnectToCoreException('Request timed out connecting to Core.');
        }

        if ($response->status() === 400) {
            throw new InvalidSiteKeyException();
        }

        if ($response->status() !== 200) {
            throw new CoreCannotConnectException('Got status code of: ' . $response->status());
        }

        $body = json_decode($response->body());
        if (!isset($body->success) || !$body->success) {
            throw new CoreCannotConnectException('Invalid response body: ' . print_r($body, true));
        }

        return $body;
    }

    public function startBackupUpload(string $siteId, string $backupId, string $checksum): \stdClass
    {
        return $this->send('GET', "site/{$siteId}/backup/{$backupId}", ['checksum' => $checksum])->data;
    }

    public function getUploadPart(string $siteId, string $backupId): \stdClass
    {
        return $this->send('POST', "site/{$siteId}/backup/{$backupId}")->data;
    }

    public function markBackupComplete(string $siteId, string $backupId, array $partChecksums): void
    {
        $this->send('PUT', "site/{$siteId}/backup/{$backupId}", [
            'success' => true,
            'part_checksums' => $partChecksums,
        ]);
    }

    public function markBackupFailed(string $siteId, string $backupId): void
    {
        $this->send('PUT', "site/{$siteId}/backup/{$backupId}", [
            'success' => false,
        ]);
    }
}