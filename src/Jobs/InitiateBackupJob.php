<?php

namespace VarsuiteCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\File;
use VarsuiteCore\Backup\DatabaseBackup;
use VarsuiteCore\Backup\RetryManager;
use VarsuiteCore\Backup\S3Uploader;
use VarsuiteCore\Backup\ZipCreator;
use VarsuiteCore\CoreApi;

class InitiateBackupJob extends CoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    private int $timeout = 60 * 60; // 1 hour

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
            $dumpFilePath = (new DatabaseBackup($this->logger()))->create();

            // Create a ZIP file containing all files
            $this->logger()->debug('Starting ZIP file creation');
            try {
                $zipFile = (new ZipCreator())->create();
            } catch (\RuntimeException $e) {
                $this->logger()->error($e->getMessage());
                throw $e;
            }
            $this->logger()->debug('ZIP file created');

            // Upload the ZIP file to storage
            $this->logger()->debug('Backup upload started');
            try {
                (new S3Uploader($api, new RetryManager(), $this->logger()))->upload($zipFile, $this->backupId);
            } catch (\RuntimeException $e) {
                $this->logger()->error(sprintf(
                    '%s: %s in %s:%d',
                    get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                ), ['exception' => $e]);

                throw $e;
            }

            $this->logger()->debug('Backup complete');
        } catch (\Throwable $e) {
            // Backup has failed
            $this->logger()->error('Backup failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            $api->abortBackupUpload($this->backupId, null, $zipFile ?? null);
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
