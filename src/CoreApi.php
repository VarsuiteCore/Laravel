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
    private const DEBUG = false;

    private string $testUrl = 'https://core.test/api';

    private string $prodUrl = 'https://app.vs-core.com/api';

    public function syncData(array $data): void
    {
        $this->send('POST', 'data', $data);
    }

    public function startBackupUpload(string $backupId, string $checksum): \stdClass
    {
        return $this->send('POST', "backup/{$backupId}/upload", ['checksum' => $checksum])->data;
    }

    public function getUploadPartUrl(string $backupId, string $uploadId, string $filename, int $partNumber): \stdClass
    {
        return $this->send('PUT', "backup/{$backupId}/upload/{$uploadId}", [
            'filename' => $filename,
            'partNumber' => $partNumber,
        ])->data;
    }

    public function completeBackupUpload(string $backupId, string $uploadId, string $filename, array $partIds, string $checksum): void
    {
        $this->send('DELETE', "backup/{$backupId}/upload/{$uploadId}", [
            'action' => 'complete',
            'filename' => $filename,
            'partIds' => $partIds,
            'checksum' => $checksum,
        ]);
    }

    public function abortBackupUpload(string $backupId, ?string $uploadId, ?string $filename): void
    {
        $this->send('DELETE', "backup/{$backupId}/upload/{$uploadId}", [
            'action' => 'abort',
            'filename' => $filename,
        ]);
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

        if ($response->status() < 200 && $response->status() > 300) {
            throw new CoreCannotConnectException('Got status code of: ' . $response->status() . ' for url ' . $url);
        }

        $body = json_decode($response->body());
        if (!isset($body->success) || !$body->success) {
            throw new CoreCannotConnectException('Invalid response body: ' . print_r($body, true));
        }

        return $body;
    }
}
