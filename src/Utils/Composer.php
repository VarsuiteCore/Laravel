<?php

namespace VarsuiteCore\Utils;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Fluent;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
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
            throw new \RuntimeException('Unable to locate the Composer binary.');
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