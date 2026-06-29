<?php

namespace Brimham\BackupMonitor\Listeners;

use Brimham\BackupMonitor\Models\BackupRun;
use Illuminate\Events\Dispatcher;
use Spatie\Backup\BackupDestination\BackupDestination;
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
 *
 * Spatie 10 reshaped every event: v9 carries BackupDestination /
 * BackupDestinationStatus objects, v10 carries plain strings (and a
 * failureMessages collection). We support both majors, so each handler reads
 * through the helpers below, which branch on the event shape.
 */
class RecordsBackupEvents
{
    public function handleSuccessfulBackup(BackupWasSuccessful $event): void
    {
        $destination = $this->destination($event);

        // Reading the destination's newest backup can fail on a flaky remote
        // disk; we still want to record that the backup itself succeeded.
        $newest = null;
        try {
            $newest = $destination?->newestBackup();
        } catch (Throwable) {
            // degrade gracefully
        }

        BackupRun::create([
            'type' => 'backup',
            'status' => 'success',
            'backup_name' => $this->backupName($event),
            'disk' => $this->diskName($event),
            'size_in_bytes' => $newest?->sizeInBytes(),
            'path' => $newest?->path(),
        ]);
    }

    public function handleFailedBackup(BackupHasFailed $event): void
    {
        BackupRun::create([
            'type' => 'backup',
            'status' => 'failed',
            'backup_name' => $this->backupName($event),
            'disk' => $this->diskName($event),
            'message' => $event->exception->getMessage(),
        ]);
    }

    public function handleHealthyBackup(HealthyBackupWasFound $event): void
    {
        BackupRun::create([
            'type' => 'health_check',
            'status' => 'healthy',
            'backup_name' => $this->backupName($event),
            'disk' => $this->diskName($event),
        ]);
    }

    public function handleUnhealthyBackup(UnhealthyBackupWasFound $event): void
    {
        BackupRun::create([
            'type' => 'health_check',
            'status' => 'unhealthy',
            'backup_name' => $this->backupName($event),
            'disk' => $this->diskName($event),
            'message' => $this->failureReason($event),
        ]);
    }

    private function diskName(object $event): ?string
    {
        if (property_exists($event, 'diskName')) {
            return $event->diskName; // v10
        }

        return $this->v9Destination($event)?->diskName();
    }

    private function backupName(object $event): ?string
    {
        if (property_exists($event, 'backupName')) {
            return $event->backupName; // v10
        }

        return $this->v9Destination($event)?->backupName();
    }

    /**
     * The BackupDestination for size/path lookup on a successful backup.
     * v9 hands it to us on the event; v10 only gives strings, so we resolve
     * it from the disk + backup name.
     */
    private function destination(BackupWasSuccessful $event): ?BackupDestination
    {
        if (! property_exists($event, 'diskName')) {
            return $event->backupDestination; // v9
        }

        try {
            return BackupDestination::create($event->diskName, $event->backupName);
        } catch (Throwable) {
            return null;
        }
    }

    private function v9Destination(object $event): ?BackupDestination
    {
        if (property_exists($event, 'backupDestinationStatus')) {
            return $event->backupDestinationStatus->backupDestination();
        }

        return $event->backupDestination ?? null;
    }

    private function failureReason(UnhealthyBackupWasFound $event): ?string
    {
        // v10: a collection of ['check' => ..., 'message' => ...].
        if (property_exists($event, 'failureMessages')) {
            return $event->failureMessages->isNotEmpty()
                ? $event->failureMessages->map(fn (array $f) => "[{$f['check']}] {$f['message']}")->implode("\n")
                : null;
        }

        // v9: the reason hangs off the health check failure. The accessor has
        // shifted across majors, so degrade gracefully if it differs.
        try {
            return $event->backupDestinationStatus->getHealthCheckFailure()?->exception()?->getMessage();
        } catch (Throwable) {
            return null;
        }
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
