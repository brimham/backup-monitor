<?php

use Brimham\BackupMonitor\Models\BackupRun;

function backupRun(string $disk, string $status, string $createdAt): void
{
    BackupRun::create([
        'type' => 'backup',
        'status' => $status,
        'disk' => $disk,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

it('returns exactly one most-recent backup run per disk', function () {
    backupRun('s3', 'success', '2026-06-01 00:00:00');
    backupRun('s3', 'failed', '2026-06-29 00:00:00');   // newest for s3
    backupRun('local', 'success', '2026-06-10 00:00:00'); // newest for local
    backupRun('local', 'success', '2026-06-02 00:00:00');

    $latest = BackupRun::latestPerDisk();

    expect($latest)->toHaveCount(2)
        ->and($latest->pluck('disk')->all())->toEqualCanonicalizing(['s3', 'local']);

    expect($latest->firstWhere('disk', 's3')->status)->toBe('failed')
        ->and($latest->firstWhere('disk', 'local')->created_at->toDateString())->toBe('2026-06-10');
});

it('ignores non-backup rows', function () {
    backupRun('s3', 'success', '2026-06-01 00:00:00');
    BackupRun::create([
        'type' => 'health_check',
        'status' => 'healthy',
        'disk' => 's3',
        'created_at' => '2026-06-29 00:00:00',
    ]);

    $latest = BackupRun::latestPerDisk();

    expect($latest)->toHaveCount(1)
        ->and($latest->first()->type)->toBe('backup');
});
