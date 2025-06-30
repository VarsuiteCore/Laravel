<?php

namespace VarsuiteCore;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use VarsuiteCore\Console\Commands\SyncCommand;
use VarsuiteCore\Console\Commands\TestCommand;
use VarsuiteCore\Listeners\SyncFallbackListener;

class VarsuiteCoreServiceProvider extends ServiceProvider
{
    private const VERSION = '0.0.4';

    public function register(): void
    {
        // Decorate Laravel's exception handler with ours
        $this->app->extend(ExceptionHandler::class, function ($handler, $app) {
            return new CoreExceptionHandler($handler);
        });
    }

    public function boot(): void
    {
        // Ensure storage directory and gitignore exists
        File::ensureDirectoryExists(storage_path('vscore'));
        if (!File::exists(storage_path('vscore/.gitignore'))) {
            File::put(storage_path('vscore/.gitignore'), <<<TEXT
*
!.gitignore
TEXT);
        }

        // Config file
        $this->publishes([
            __DIR__.'/../config/vscore.php' => config_path('vscore.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../config/vscore.php', 'vscore'
        );

        // Migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Routing
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // About artisan command
        AboutCommand::add('Varsuite Core', function() {
            return ['Version' => self::VERSION];
        });

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncCommand::class,
                TestCommand::class,
            ]);
        }

        // Task scheduling
        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            $schedule
                ->command('vscore:sync')
                ->everyMinute()
                ->onOneServer()
                ->withoutOverlapping();
        });

        // Event fallback if task scheduler has not been running
        Event::listen(RequestHandled::class, SyncFallbackListener::class);
    }
}