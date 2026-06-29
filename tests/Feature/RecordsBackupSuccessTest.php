<?php

use Brimham\BackupMonitor\Listeners\RecordsBackupEvents;
use Brimham\BackupMonitor\Models\BackupRun;
use Illuminate\Support\Facades\Storage;

it('records a successful backup, resolving size and path from the destination', function () {
    Storage::fake('backups');
    Storage::disk('backups')->put('my-app/2026-06-29-12-00-00.zip', 'backup-bytes');

    (new RecordsBackupEvents)->handleSuccessfulBackup(makeSuccessEvent('backups', 'my-app'));

    expect(BackupRun::count())->toBe(1);

    $run = BackupRun::first();
    expect($run->type)->toBe('backup')
        ->and($run->status)->toBe('success')
        ->and($run->backup_name)->toBe('my-app')
        ->and($run->disk)->toBe('backups')
        ->and($run->size_in_bytes)->toBe(strlen('backup-bytes'))
        ->and($run->path)->toBe('my-app/2026-06-29-12-00-00.zip')
        ->and($run->wasSuccessful())->toBeTrue();
});

it('still records success when no backup file can be resolved', function () {
    Storage::fake('backups');

    (new RecordsBackupEvents)->handleSuccessfulBackup(makeSuccessEvent('backups', 'my-app'));

    $run = BackupRun::first();
    expect($run->status)->toBe('success')
        ->and($run->disk)->toBe('backups')
        ->and($run->size_in_bytes)->toBeNull()
        ->and($run->path)->toBeNull();
});
