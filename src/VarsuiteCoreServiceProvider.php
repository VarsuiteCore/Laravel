<?php

namespace VarsuiteCore;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use VarsuiteCore\Console\Commands\SyncCommand;
use VarsuiteCore\Console\Commands\TestCommand;

class VarsuiteCoreServiceProvider extends ServiceProvider
{
    private const VERSION = '0.0.1';

    public function boot(): void
    {
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
    }
}