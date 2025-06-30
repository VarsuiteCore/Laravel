<?php

namespace VarsuiteCore\Utils;

use Mockery;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use VarsuiteCore\Factories\ProcessFactory;

class ComposerTest extends TestCase
{
    public function test_installed(): void
    {
        $processFactory = $this->mockProcess('show', [
            '--direct', '--format=json'
        ], json_encode(['installed' => []]));
        $actual = (new Composer($processFactory))->installed();
        $this->assertEquals(collect([]), $actual);

        $processFactory = $this->mockProcess('show', [
            '--direct', '--format=json'
        ],
        <<<JSON
{
    "installed": [
        {
            "name": "fakerphp/faker",
            "direct-dependency": true,
            "homepage": null,
            "source": "https://github.com/FakerPHP/Faker/tree/v1.24.1",
            "version": "v1.24.1",
            "description": "Faker is a PHP library that generates fake data for you.",
            "abandoned": false
        }
    ]
}
JSON
        );
        $actual = (new Composer($processFactory))->installed();
        $this->assertEquals(collect([
            [
                'name' => 'fakerphp/faker',
                'direct-dependency' => true,
                'homepage' => null,
                'source' => 'https://github.com/FakerPHP/Faker/tree/v1.24.1',
                'version' => 'v1.24.1',
                'description' => 'Faker is a PHP library that generates fake data for you.',
                'abandoned' => false
            ]
        ]), $actual);
    }

    public function test_availableUpdates(): void
    {
        $processFactory = $this->mockProcess('outdated', ['--format=json'],
        <<<JSON
{
    "installed": [
        {
            "name": "phpunit/phpunit",
            "direct-dependency": false,
            "homepage": "https://phpunit.de/",
            "source": "https://github.com/sebastianbergmann/phpunit/tree/11.5.15",
            "version": "11.5.15",
            "release-age": "3 months old",
            "release-date": "2025-03-23T16:02:11+00:00",
            "latest": "11.5.25",
            "latest-status": "semver-safe-update",
            "latest-release-date": "2025-06-27T04:36:07+00:00",
            "description": "The PHP Unit Testing framework.",
            "abandoned": false
        }
    ]
}
JSON
        );
        $actual = (new Composer($processFactory))->availableUpdates();
        $this->assertEquals(collect([
            'phpunit/phpunit' => '11.5.25',
        ]), $actual);
    }

    public function test_updateCore(): void
    {
        $processFactory = $this->mockProcess('update', ['laravel/*', 'illuminate/*', '--with-all-dependencies', '--optimize-autoloader', '--no-progress']);
        (new Composer($processFactory))->updateCore();
    }

    public function test_update(): void
    {
        $processFactory = $this->mockProcess('require', ['--with-all-dependencies', '--optimize-autoloader', '--no-progress', 'phpunit/phpunit']);
        (new Composer($processFactory))->update(['phpunit/phpunit']);
    }

    public function test_remove(): void
    {
        $processFactory = $this->mockProcess('remove', ['--optimize-autoloader', '--no-progress', 'phpunit/phpunit']);
        (new Composer($processFactory))->remove(['phpunit/phpunit']);
    }

    private function mockProcess(string $expectedCommand, array $expectedArgs, ?string $mockOutput = null)
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('setWorkingDirectory')->once()->with(base_path())->andReturnSelf();
        $process->shouldReceive('setTimeout')->once()->with(120)->andReturnSelf();
        $process->shouldReceive('mustRun')->once();
        $process->shouldReceive('getOutput')->once()->andReturn($mockOutput ?? '');

        $php = (new PhpExecutableFinder())->find();
        $composer = (new ExecutableFinder())->find('composer');
        $processFactory = Mockery::mock(ProcessFactory::class);
        $processFactory
            ->shouldReceive('__invoke')
            ->once()
            ->with([
                $php,
                $composer,
                $expectedCommand,
                '--no-interaction',
                ...$expectedArgs,
            ])
            ->ordered()
            ->andReturn($process);

        return $processFactory;
    }
}
