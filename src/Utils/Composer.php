<?php

namespace VarsuiteCore\Utils;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Fluent;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use VarsuiteCore\Factories\ProcessFactory;

/**
 * Utility class to aid in interfacing with Composer.
 */
class Composer
{
    private ProcessFactory $factory;

    public function __construct(ProcessFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Get data about all currently installed packages.
     */
    public function installed(): Collection
    {
        $installed = $this->process('show', '--direct', '--format=json');

        return collect(json_decode($installed, associative: true, flags: JSON_THROW_ON_ERROR)['installed']);
    }

    /**
     * Get all currently available Composer package updates.
     */
    public function availableUpdates(): Collection
    {
        $installed = $this->process('outdated', '--format=json');

        return collect(json_decode($installed, associative: true, flags: JSON_THROW_ON_ERROR)['installed'])
            ->keyBy('name')
            ->map(function ($package) {
                return $package['latest'];
            })
            ->filter(function ($version) {
                return $version !== '[none matched]';
            });
    }

    /**
     * Update Laravel's core packages to their latest installable versions.
     */
    public function updateCore(): void
    {
        $this->process('update', 'laravel/*', 'illuminate/*', '--with-all-dependencies', '--optimize-autoloader', '--no-progress');
    }

    /**
     * Update packages via the given identifiers to their latest installable versions.
     */
    public function update(array $identifiers): void
    {
        $this->process('require', '--with-all-dependencies', '--optimize-autoloader', '--no-progress',  ...$identifiers);
    }

    /**
     * Remove packages via the given identifiers.
     */
    public function remove(array $identifiers): void
    {
        $this->process('remove', '--optimize-autoloader', '--no-progress', ...$identifiers);
    }

    /**
     * Decode composer.json at the provided path.
     */
    public function decodeJson(string $path): Fluent
    {
        return new Fluent(json_decode(File::get($path)));
    }

    /**
     * Locate and return the PHP binary.
     */
    private function php(): string
    {
        $binary = (new PhpExecutableFinder())->find();
        if (!$binary) {
            throw new \RuntimeException('Unable to locate the PHP binary.');
        }

        return $binary;
    }

    /**
     * Locate and return the Composer binary.
     */
    private function composer(): string
    {
        $binary = (new ExecutableFinder())->find('composer');
        if (!$binary) {
            if (!File::exists(storage_path('vscore/composer'))) {
                // Download composer into Laravel's storage directory
                File::ensureDirectoryExists(storage_path('vscore'));
                if (!File::exists(storage_path('vscore/.gitignore'))) {
                    File::put(storage_path('vscore/.gitignore'), <<<TEXT
*
!.gitignore
TEXT
                    );
                }

                $expectedChecksum = Http::throw()->get('https://composer.github.io/installer.sig')->body();
                copy('https://getcomposer.org/installer', storage_path('vscore/composer-setup.php'));
                $actualChecksum = hash_file('sha384', storage_path('vscore/composer-setup.php'));
                if ($expectedChecksum !== $actualChecksum) {
                    File::delete(storage_path('vscore/composer-setup.php'));
                    throw new \RuntimeException('Composer checksum mismatch.');
                }
                Process::fromShellCommandline($this->php() . ' ' . storage_path('vscore/composer-setup.php') . ' --install-dir=' . storage_path('vscore') . ' --filename=composer')
                    ->mustRun();
                if (!File::exists(storage_path('vscore/composer'))) {
                    File::delete(storage_path('vscore/composer-setup.php'));
                    throw new \RuntimeException('Composer binary not installed correctly.');
                }
                File::delete(storage_path('vscore/composer-setup.php'));
            }

            $binary = storage_path('vscore/composer');
            Process::fromShellCommandline($this->php() . ' ' . $binary . ' self-update --no-interaction');
        }

        return $binary;
    }

    /**
     * Run a new Composer process
     */
    private function process(string $command, string ...$args): string
    {
        $process = ($this->factory)([
            $this->php(),
            $this->composer(),
            $command,
            '--no-interaction',
            ...$args,
        ]);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(120);
        $process->mustRun();

        return $process->getOutput();
    }
}