<?php

namespace Brimham\BackupMonitor\Listeners;

use Brimham\BackupMonitor\Models\BackupRun;
use Illuminate\Events\Dispatcher;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Throwable;

/**
 * Listens to spatie/laravel-backup's events and writes a row to backup_runs
 * for each one. This is the whole free-tier engine: a durable history of
 * what actually happened, which neither Spatie nor the existing Filament
 * plugins keep.
 */
class RecordsBackupEvents
{
    public function handleSuccessfulBackup(BackupWasSuccessful $event): void
    {
        $destination = $event->backupDestination;

        // Reading the destination's newest backup can fail on a flaky remote
        // disk; we still want to record that the backup itself succeeded.
        $newest = null;
        try {
            $newest = $destination->newestBackup();
        } catch (Throwable) {
            // degrade gracefully
        }

        BackupRun::create([
            'type' => 'backup',
            'status' => 'success',
            'backup_name' => $destination->backupName(),
            'disk' => $destination->diskName(),
            'size_in_bytes' => $newest?->sizeInBytes(),
            'path' => $newest?->path(),
        ]);
    }

    public function handleFailedBackup(BackupHasFailed $event): void
    {
        BackupRun::create([
            'type' => 'backup',
            'status' => 'failed',
            'backup_name' => $event->backupDestination?->backupName(),
            'disk' => $event->backupDestination?->diskName(),
            'message' => $event->exception->getMessage(),
        ]);
    }

    public function handleHealthyBackup(HealthyBackupWasFound $event): void
    {
        $destination = $event->backupDestinationStatus->backupDestination();

        BackupRun::create([
            'type' => 'health_check',
            'status' => 'healthy',
            'backup_name' => $destination->backupName(),
            'disk' => $destination->diskName(),
        ]);
    }

    public function handleUnhealthyBackup(UnhealthyBackupWasFound $event): void
    {
        $status = $event->backupDestinationStatus;
        $destination = $status->backupDestination();

        // NOTE: the accessor for the failure reason has shifted across Spatie
        // major versions. Confirm this against the version you target.
        $reason = null;
        try {
            $reason = $status->getHealthCheckFailure()?->exception()?->getMessage();
        } catch (Throwable) {
            // degrade gracefully if the accessor differs in your version
        }

        BackupRun::create([
            'type' => 'health_check',
            'status' => 'unhealthy',
            'backup_name' => $destination->backupName(),
            'disk' => $destination->diskName(),
            'message' => $reason,
        ]);
    }

    /**
     * Map Spatie's events to the handlers above.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            BackupWasSuccessful::class => 'handleSuccessfulBackup',
            BackupHasFailed::class => 'handleFailedBackup',
            HealthyBackupWasFound::class => 'handleHealthyBackup',
            UnhealthyBackupWasFound::class => 'handleUnhealthyBackup',
        ];
    }
}
