<?php

namespace VarsuiteCore\Backup;

use ZipArchive;

/**
 * Creates the backup zip file.
 */
class ZipCreator
{
    /**
     * @throws \RuntimeException When it fails to create a new zip file.
     *
     * @return string Path to a newly created zip file.
     */
    public function create(): string
    {
        $zip = new ZipArchive();
        $domain = parse_url(config('app.url'), PHP_URL_HOST);
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
            throw new \RuntimeException('Failed to create ZIP file');
        }

        return $zipFile;
    }
}
