# Brimham Backup Monitor

Records and surfaces the health and history of your [spatie/laravel-backup](https://github.com/spatie/laravel-backup) runs.

Spatie's backup package and the existing Filament backup plugins are **stateless** — they let you create, download, and delete backups, but they keep no history of what actually happened. This package adds the missing layer: a durable record of every backup run, so you can see at a glance whether your backups are actually working.

This is the free core: it records runs and exposes them for display. Proactive alerting on missed/silent failures and a multi-site dashboard live in Pro — see [Upgrade to Pro](#upgrade-to-pro).

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

## Upgrade to Pro

This core package answers *"did my backups run?"* after the fact. It can't tell you when a
scheduled backup **silently stops running** — there's no event to record, so nothing lands in the
table. That's the failure that actually loses data, and it's what
[**Brimham Backup Monitor Pro**](https://brimham.app/plugins/backup-monitor-pro) is built to catch.

Pro builds on this package and adds:

- **Missed / overdue detection** — a dead-man's-switch that flags any destination whose last
  successful backup is older than its expected cadence (or has never run).
- **Multi-channel alerting** — mail, Slack, Discord, Teams, and a generic webhook, with
  de-duplication so you're told once, not on every check.
- **Escalation & recovery** — widen the alert the longer a backup stays down, and get told when
  it's healthy again.
- **External heartbeat** — ping healthchecks.io / Cronitor as backups run, so an outage is caught
  even if the whole app and its scheduler are down.
- **Multi-site dashboard** — push every install's health to a self-hosted
  [collector](https://github.com/brimham/filament-backup-monitor-collector) and watch all your
  sites from one panel. Ideal for agencies.

[**Get Pro →**](https://brimham.app/plugins/backup-monitor-pro)

## License

MIT. See [LICENSE](LICENSE).
