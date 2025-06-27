<?php

namespace VarsuiteCore;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Fluent;
use Symfony\Component\Process\Process;

/**
 * Gathers all sync data about this Laravel installation.
 *
 * @internal
 */
class LaravelEnvironment
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function toArray(): array
    {
        return [
            'domain' => $this->domain(),
            'type' => 'laravel',
            'version' => $this->version(),
            'php_version' => $this->phpVersion(),
            'maintenance_mode' => $this->maintenanceMode(),
            'packages' => $this->packages(),
            'users' => $this->users(),
            'errorLogs' => $this->errorLogs(),
            'applicationHealth' => $this->applicationHealth(),
        ];
    }

    private function domain(): string
    {
        return parse_url(config('app.url'), PHP_URL_HOST);
    }

    private function version(): string
    {
        return $this->app->version();
    }

    public function phpVersion(): string
    {
        return phpversion();
    }

    private function maintenanceMode(): bool
    {
        return $this->app->isDownForMaintenance();
    }

    public function packages(): array
    {
        if (!File::exists(base_path('composer.json'))) {
            return [];
        }

        $packages = [];
        $installed = Process::fromShellCommandline('composer show --direct --format=json')
            ->mustRun()
            ->getOutput();
        $installed = (new Fluent(json_decode($installed)))->array('installed');

        $updates = Process::fromShellCommandline('composer outdated --format=json')
            ->mustRun()
            ->getOutput();
        $updates = (new Fluent(json_decode($updates)))
            ->collect('installed')
            ->keyBy('name')
            ->map(function ($package) {
                return $package->latest;
            })
            ->filter(function ($version) {
                return $version !== '[none matched]';
            });

        foreach ($installed as $package) {
            $package = new Fluent($package);

            $authors = [];
            if (File::exists(base_path("vendor/{$package->string('name')}/composer.json"))) {
                $composerJson = new Fluent(json_decode(File::get(base_path("vendor/{$package->string('name')}/composer.json"))));

                foreach ($composerJson->array('authors') as $author) {
                    $authors[] = $author->name;
                }
            }
            $authors = implode(', ', $authors);
            if (blank($authors)) {
                $authors = null;
            }
            $url = $package->string('homepage')->toString();
            if (blank($url)) {
                $url = $package->string('source')->toString();
            }
            if (blank($url)) {
                $url = null;
            }

            $packages[] = [
                'identifier' => $package->string('name')->toString(),
                'type' => 'dependency',
                'name' => $package->string('name')->toString(),
                'enabled' => true,
                'url' => $url,
                'author' => $authors,
                'version' => $package->string('version')->toString(),
                'update_version' => $updates->get($package->string('name')->toString()),
            ];
        }

        return $packages;
    }

    public function users(): array
    {
        return [
            'users' => User::all()->map(function ($user) {
                return [
                    'id' => $user->getKey(),
                    'display_name' => $user->name,
                    'username' => $user->email,
                    'email' => $user->email,
                    'role' => null,
                    'created_at' => $user->created_at->toIso8601String(),
                ];
            }),
            'roles' => [],
        ];
    }

    public function errorLogs(): array
    {
        DB::table('vscore_error_logs') // @todo remove
            ->insert([
                'code' => 1234,
                'file' => 'test.php',
                'line' => 123,
                'message' => 'Testing',
                'last_occurrence' => now(),
            ]);
        $records = DB::table('vscore_error_logs')
            ->latest('last_occurrence')
            ->limit(100)
            ->get();
        $toDelete = $records->map(function ($record) { return $record->id; });
        DB::table('vscore_error_logs')
            ->whereIn('id', $toDelete)
            ->delete();

        return $records->toArray();
    }

    public function applicationHealth(): array
    {
        $applicationHealth = [];

        // Database response time
        try {
            $responseTimeMs = Benchmark::measure(function () {
                DB::table('vscore_error_logs')->count();
            });

            $applicationHealth['db_response_time'] = [
                'status' => $responseTimeMs > 100 ? 'danger' : 'success',
                'data' => ['value' => $responseTimeMs],
            ];
        } catch (\Throwable $e) {
        }

        // @todo Database disk usage?

        // Debug mode
        try {
            $debugMode = config('app.debug');
            $applicationHealth['debug_mode'] = [
                'status' => $debugMode ? 'danger' : 'success',
                'data' => ['value' => $debugMode],
            ];
        } catch (\Throwable $e) {
        }

        // CPU Load (universal)
        try {
            $load = null;
            if (function_exists('sys_getloadavg')) {
                $loadAvg = sys_getloadavg();
                if ($loadAvg && is_array($loadAvg)) {
                    $load = $loadAvg[0]; // 1-minute average
                }
            }

            if (stripos(PHP_OS, 'WIN') === false) {
                if (is_file('/proc/cpuinfo')) {
                    $cpuCores = substr_count(file_get_contents('/proc/cpuinfo'), 'processor');
                } elseif (function_exists('shell_exec')) {
                    $cpuCores = (int) shell_exec("nproc 2>/dev/null");
                }
            } elseif (function_exists('shell_exec')) {
                $output = shell_exec("wmic cpu get NumberOfLogicalProcessors 2>NUL");
                $lines = array_filter(array_map('trim', explode("\n", $output)));
                $cpuCores = isset($lines[1]) ? (int) $lines[1] : 1;
            }

            $cpuCores = max(1, (int) $cpuCores);

            if ($load !== null) {
                $percent = round(($load / $cpuCores) * 100, 2);
                $applicationHealth['used_cpu'] = [
                    'status' => $percent > 90 ? 'danger' : 'success',
                    'data' => ['load' => $load, 'percent' => $percent],
                ];
            }
        } catch (\Throwable $e) {
        }

        // Disk usage
        try {
            $totalBytes = @disk_total_space(base_path());
            $freeBytes = @disk_free_space(base_path());

            if ($totalBytes && $freeBytes) {
                $applicationHealth['used_disk'] = [
                    'status' => ($totalBytes - $freeBytes) < 1024 * 1024 * 1024 ? 'danger' : 'success',
                    'data' => [
                        'used_mb' => round(($totalBytes - $freeBytes) / 1024 / 1024, 2),
                        'total_mb' => round($totalBytes / 1024 / 1024, 2),
                    ],
                ];
            }
        } catch (\Throwable $e) {
        }

        // Memory usage (cross-platform using PHP built-ins)
        try {
            if (function_exists('memory_get_usage') && function_exists('memory_get_peak_usage')) {
                $used = memory_get_usage(true);
                $peak = memory_get_peak_usage(true);
                $applicationHealth['used_memory'] = [
                    'status' => $used > 1024 * 1024 * 1024 ? 'danger' : 'success',
                    'data' => [
                        'used_mb' => round($used / 1024 / 1024, 2),
                        'peak_mb' => round($peak / 1024 / 1024, 2),
                    ],
                ];
            }
        } catch (\Throwable $e) {
        }

        return $applicationHealth;
    }
}