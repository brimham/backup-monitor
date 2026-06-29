<?php

use Brimham\BackupMonitor\Listeners\RecordsBackupEvents;
use Brimham\BackupMonitor\Models\BackupRun;

it('records a failed backup with the exception message', function () {
    (new RecordsBackupEvents)->handleFailedBackup(makeFailedEvent(new Exception('disk full'), 's3', 'my-app'));

    $run = BackupRun::first();
    expect($run->type)->toBe('backup')
        ->and($run->status)->toBe('failed')
        ->and($run->backup_name)->toBe('my-app')
        ->and($run->disk)->toBe('s3')
        ->and($run->message)->toBe('disk full')
        ->and($run->wasSuccessful())->toBeFalse();
});

it('records a failure even when no disk is attached', function () {
    (new RecordsBackupEvents)->handleFailedBackup(makeFailedEvent(new Exception('config error')));

    $run = BackupRun::first();
    expect($run->status)->toBe('failed')
        ->and($run->disk)->toBeNull()
        ->and($run->message)->toBe('config error');
});
