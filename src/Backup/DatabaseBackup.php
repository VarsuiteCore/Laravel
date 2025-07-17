<?php

namespace VarsuiteCore\Backup;

use Illuminate\Support\Facades\File;
use Psr\Log\LoggerInterface;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbDumper\Databases\Sqlite;

/**
 * Creates a database dump for adding to the backup process.
 */
class DatabaseBackup
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function create(): ?string
    {
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

        if (null === $dumper) {
            $this->logger->debug("Skipping database dump, driver {$driver} is not supported");
            return null;
        }

        $dumpFilePath = storage_path('vscore/' . $domain . '.sql');
        $dumper->dumpToFile($dumpFilePath);
        $this->logger->debug('Database dump complete');

        return $dumpFilePath;
    }
}
