<?php

use Brimham\BackupMonitor\Listeners\RecordsBackupEvents;
use Brimham\BackupMonitor\Models\BackupRun;

it('records a healthy backup as a health_check', function () {
    (new RecordsBackupEvents)->handleHealthyBackup(makeHealthyEvent('s3', 'my-app'));

    $run = BackupRun::first();
    expect($run->type)->toBe('health_check')
        ->and($run->status)->toBe('healthy')
        ->and($run->disk)->toBe('s3')
        ->and($run->backup_name)->toBe('my-app')
        ->and($run->message)->toBeNull();
});

it('records an unhealthy backup and captures the failure reasons', function () {
    $failures = [
        ['check' => 'MaximumAgeInDays', 'message' => 'backup is 3 days old'],
        ['check' => 'MaximumStorageInMegabytes', 'message' => 'storage exceeded'],
    ];

    (new RecordsBackupEvents)->handleUnhealthyBackup(makeUnhealthyEvent('s3', 'my-app', $failures));

    $run = BackupRun::first();
    expect($run->type)->toBe('health_check')
        ->and($run->status)->toBe('unhealthy')
        ->and($run->disk)->toBe('s3')
        ->and($run->message)->toBe(expectedUnhealthyMessage($failures));
});
