<?php

namespace VarsuiteCore\Backup;

use Psr\Log\LoggerInterface;
use VarsuiteCore\CoreApi;

/**
 * Uploads the backup zip in parts to an S3-compatible storage provider.
 */
class S3Uploader
{
    private CoreApi $api;
    private RetryManager $retryManager;
    private LoggerInterface $logger;
    private int $partSize = 100 * 1024 * 1024; // 100MB
    private ?string $filename = null;
    private ?string $uploadId = null;

    private array $partIds = [];

    public function __construct(
        CoreApi $api,
        RetryManager $retryManager,
        LoggerInterface $logger,
    ) {
        $this->api = $api;
        $this->retryManager = $retryManager;
        $this->logger = $logger;
    }

    public function upload(string $zipPath, string $backupId): bool
    {
        if (!file_exists($zipPath) || !is_readable($zipPath)) {
            throw new \RuntimeException("File not found or not readable: {$zipPath}");
        }

        $fileSize = filesize($zipPath);
        if ($fileSize === false || $fileSize === 0) {
            throw new \RuntimeException("Unable to determine file size or file is empty: {$zipPath}");
        }

        // Calculate file checksum
        $checksum = hash_file('sha256', $zipPath);

        try {
            $this->startMultipartUpload($backupId, $checksum);
            $this->logger->debug("Started");
            $this->uploadParts($zipPath, $fileSize, $this->partSize, $backupId);
            $this->completeMultipartUpload($backupId, $checksum);

            return true;
        } catch (\Throwable $e) {
            if ($this->uploadId) {
                $this->abortMultipartUpload($backupId);
            }

            throw $e;
        } finally {
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
        }
    }

    private function startMultipartUpload(string $backupId, string $checksum): void
    {
        $response = $this->retryManager->execute(function () use ($backupId, $checksum) {
            return $this->api->startBackupUpload($backupId, $checksum);
        });
        $this->logger->debug(json_encode($response));

        $this->filename = $response->filename;
        $this->uploadId = $response->uploadId;
    }

    private function uploadParts(string $zipPath, int $fileSize, int $partSize, string $backupId): void
    {
        try {
            $totalParts = ceil($fileSize / $partSize);
            $handle = fopen($zipPath, 'rb');

            for ($partNumber = 1; $partNumber <= $totalParts; $partNumber++) {
                $this->logger->debug("Start part: {$partNumber}");
                $offset = ($partNumber - 1) * $partSize;
                $length = min($partSize, $fileSize - $offset);

                fseek($handle, $offset);
                $partData = fread($handle, $length);
                $checksum = md5($partData, true);

                $this->retryManager->execute(function () use ($backupId, $partData, $partNumber, $checksum) {
                    $response = $this->api->getUploadPartUrl($backupId, $this->uploadId, $this->filename, $partNumber);

                    $this->logger->debug($response->url);
                    $ch = curl_init($response->url);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $partData);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HEADER, 1);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Length: ' . strlen($partData),
                        'Expect:', // Disable Expect header
                        'ContentMD5: ' . base64_encode($checksum),
                    ]);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

                    // More permissive SSL settings for fallback
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_DEFAULT);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 60 * 60);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
                    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);

                    // Extract response headers
                    $headers = [];
                    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$headers) {
                        $len = strlen($header);
                        $header = explode(':', $header, 2);
                        if (count($header) < 2) {
                            // Ignore invalid headers
                            return $len;
                        }

                        $headers[strtolower(trim($header[0]))] = trim(str_replace('"', '', $header[1]));

                        return $len;
                    });

                    $result = curl_exec($ch);
                    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                    if ($result === false) {
                        $error = curl_error($ch);
                        curl_close($ch);
                        $this->logger->error("CURL error: {$error}");
                        throw new \RuntimeException("CURL error: {$error}");
                    }

                    curl_close($ch);

                    if ($statusCode !== 200) {
                        $this->logger->error("Failed to upload part {$partNumber}: ({$statusCode}) {$result}");
                        throw new \RuntimeException("Failed to upload part {$partNumber}");
                    }

                    $this->partIds[] = $headers['etag'];

                    return $result;
                });

                // Free up memory
                unset($partData);

                $this->logger->debug("Uploaded part {$partNumber} of {$totalParts}");
            }
        } finally {
            if (isset($handle)) {
                fclose($handle);
            }
        }
    }

    private function completeMultipartUpload(string $backupId, string $checksum): void
    {
        $this->retryManager->execute(function () use ($backupId, $checksum) {
            $this->api->completeBackupUpload($backupId, $this->uploadId, $this->filename, $this->partIds, $checksum);
        });

        $this->logger->debug("Upload complete");
    }

    private function abortMultipartUpload(string $backupId): void
    {
        $this->retryManager->execute(function () use ($backupId) {
            $this->api->abortBackupUpload($backupId, $this->uploadId, $this->filename);
        });

        $this->logger->debug("Upload aborted");
    }
}
