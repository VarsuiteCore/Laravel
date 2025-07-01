<?php

namespace VarsuiteCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\File;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbDumper\Databases\Sqlite;
use VarsuiteCore\CoreApi;
use ZipArchive;

class InitiateBackupJob extends CoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        private string $siteId,
        private string $backupId,
    )
    {
    }

    public function handle(CoreApi $api): void
    {
        try {
            $this->logger()->debug('Initiating backup for site ID ' . $this->siteId . ' with backup ID ' . $this->backupId);

            // Take a database dump, place it into storage
            File::ensureDirectoryExists(storage_path('vscore'));
            if (!File::exists(storage_path('vscore/.gitignore'))) {
                File::put(storage_path('vscore/.gitignore'), <<<TEXT
*
!.gitignore
TEXT);
            }
            $domain = parse_url(config('app.url'), PHP_URL_HOST);
            $default = config('database.default');
            $driver = config("database.connections.{$default}.driver");
            $dumper = match ($driver) {
                'sqlite' => Sqlite::create()
                    ->setDbName(config("database.connections.{$default}.database")),
                'mysql', 'mariadb' => MySql::create()
                    ->setDbName(config("database.connections.{$default}.database"))
                    ->setHost(config("database.connections.{$default}.host"))
                    ->setPort(config("database.connections.{$default}.port"))
                    ->setUserName(config("database.connections.{$default}.username"))
                    ->setPassword(config("database.connections.{$default}.password"))
                    ->setSocket(config("database.connections.{$default}.unix_socket"))
                    ->setDefaultCharacterSet(config("database.connections.{$default}.charset")),
                'pgsql' => PostgreSql::create()
                    ->setDbName(config("database.connections.{$default}.database"))
                    ->setHost(config("database.connections.{$default}.host"))
                    ->setPort(config("database.connections.{$default}.port"))
                    ->setUserName(config("database.connections.{$default}.username"))
                    ->setPassword(config("database.connections.{$default}.password")),
                default => null,
            };
            if (null !== $dumper) {
                $dumpFilePath = storage_path('vscore/' . $domain . '.sql');
                $dumper->dumpToFile($dumpFilePath);
                $this->logger()->debug('Database dump complete');
            } else {
                $this->logger()->debug("Skipping database dump, driver {$driver} is not supported");
            }

            // Create a ZIP file containing all files
            $this->logger()->debug('Starting ZIP file creation');
            $zip = new ZipArchive();
            $zipFile = storage_path('vscore/' . $domain . '-' . date('Y-m-d_H:i:s') . '.zip');

            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(base_path()),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                /** @var \SplFileInfo $file */
                foreach ($files as $file) {
                    if (!$file->isDir() && !preg_match('/\/storage\/vscore\/' . $domain . '-.*\.zip/i', $file->getRealPath())) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen(base_path()));
                        $zip->addFile($filePath, $relativePath);
                    }
                }

                $zip->close();
            } else {
                $this->logger()->error('Failed to create ZIP file');
                throw new \RuntimeException('Failed to create ZIP file');
            }
            $this->logger()->debug('ZIP file created');

            // Tell Core we are starting the ZIP file upload
            $checksum = sha1_file($zipFile);
            $response = (new CoreApi())->startBackupUpload($this->siteId, $this->backupId, $checksum);
            $partSize = $response->part_size;
            $this->logger()->debug('Backup upload started');

            // Upload ZIP file in parts to API
            $localFileSize = filesize($zipFile);
            $totalBytesSent = 0;
            $partChecksums = [];
            $partNumber = 1;
            $fileHandle = fopen($zipFile, 'rb');

            while ($totalBytesSent < $localFileSize) {
                // Ask Core for an upload part
                $response = (new CoreApi())->getUploadPart($this->siteId, $this->backupId);

                // Upload to given part details
                $this->logger()->debug('Uploading backup part ' . $partNumber);
                $bytesForPart = min($localFileSize - $totalBytesSent, $partSize) - 1;
                if ($bytesForPart < 1) {
                    $bytesForPart = 1;
                }
                fseek($fileHandle, $totalBytesSent);
                $partData = fread($fileHandle, $bytesForPart);
                $partChecksums[] = $partChecksum = sha1($partData);
                fseek($fileHandle, $totalBytesSent);

                $ch = curl_init($response->uploadUrl);
                curl_setopt($ch, CURLOPT_UPLOAD, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_INFILE, $fileHandle);
                curl_setopt($ch, CURLOPT_INFILESIZE, $bytesForPart);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'X-Bz-Content-Sha1: ' . $partChecksum,
                    'X-Bz-Part-Number: ' . $partNumber,
                    'Authorization: ' . $response->authorizationToken,
                    'Content-Type: application/zip',
                    'Content-Length: ' . $bytesForPart,
                    'Transfer-Encoding:', // Explicitly disable chunked encoding
                    'Expect:', // Disable Expect: 100-continue
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);

                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if (200 !== $statusCode) {
                    $this->logger()->error('Failed to upload backup part: ' . $response);
                    throw new \RuntimeException('Failed to upload backup part: ' . $response);
                }

                $partNumber++;
                $totalBytesSent += $bytesForPart;
            }
            fclose($fileHandle);
            $this->logger()->debug('Backup file uploaded');

            // Notify Core that the backup is complete
            $api->markBackupComplete($this->siteId, $this->backupId, $partChecksums);

            $this->logger()->debug('Backup complete');
        } catch (\Throwable $e) {
            // Backup has failed
            $this->logger()->error('Backup failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            $api->markBackupFailed($this->siteId, $this->backupId);
        } finally {
            // Clean up temporary files
            if (isset($dumpFilePath) && File::exists($dumpFilePath))
            {
                File::delete($dumpFilePath);
            }
            if (isset($zipFile) && File::exists($zipFile))
            {
                File::delete($zipFile);
            }
        }
    }
}
