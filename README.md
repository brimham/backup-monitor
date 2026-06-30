# Brimham Backup Monitor

Records and surfaces the health and history of your [spatie/laravel-backup](https://github.com/spatie/laravel-backup) runs.

Spatie's backup package and the existing Filament backup plugins are **stateless** — they let you create, download, and delete backups, but they keep no history of what actually happened. This package adds the missing layer: a durable record of every backup run, so you can see at a glance whether your backups are actually working.

This is the free core. It records runs and exposes them for display. Alerting on missed/silent failures, multi-channel notifications, and a multi-site dashboard are part of the separate Pro package.

> **Using Filament?** [`brimham/filament-backup-monitor`](https://github.com/brimham/filament-backup-monitor) adds a ready-made panel on top of this package — a run-history table and a "last backup per destination" health view.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- spatie/laravel-backup 9 or 10

## Installation

```bash
composer require brimham/backup-monitor
```

The package auto-registers its service provider. Run the migration to create the `backup_runs` table:

```bash
php artisan migrate
```

## What it does

Once installed, it listens to Spatie's backup events and writes a row to `backup_runs` for each one:

- successful backups (with destination disk, size, and path)
- failed backups (with the failure message)
- healthy / unhealthy health-check results

That history is the foundation for a "last backup per destination" health view and a run log.

## Reading the data

```php
use Brimham\BackupMonitor\Models\BackupRun;

// Most recent run per destination disk (powers the health panel)
$latest = BackupRun::latestPerDisk();

// Recent failures
$failures = BackupRun::query()->failed()->latest()->take(20)->get();
```

## License

MIT. See [LICENSE](LICENSE).
