<?php

namespace Brimham\BackupMonitor;

use Brimham\BackupMonitor\Listeners\RecordsBackupEvents;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class BackupMonitorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Ship the migration with the package so a host app gets the table
        // on `php artisan migrate`. Add ->publishesMigrations() later if you
        // want hosts to be able to customise it.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Start recording backup events the moment the package boots.
        Event::subscribe(RecordsBackupEvents::class);
    }
}
