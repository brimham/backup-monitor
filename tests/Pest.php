<?php

use Brimham\BackupMonitor\Tests\TestCase;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatus;
use Spatie\Backup\Tasks\Monitor\HealthCheckFailure;

uses(TestCase::class)->in('Feature');

/*
 * Spatie 10 reshaped its events from carrying BackupDestination /
 * BackupDestinationStatus objects (v9) to plain strings (v10). These factories
 * build the right shape for whichever major is installed so one suite runs
 * green across the whole CI matrix.
 */

function spatieEventsUseStrings(): bool
{
    $first = (new ReflectionMethod(BackupWasSuccessful::class, '__construct'))->getParameters()[0];

    return (string) $first->getType() === 'string';
}

function makeSuccessEvent(string $disk, string $name): BackupWasSuccessful
{
    return spatieEventsUseStrings()
        ? new BackupWasSuccessful($disk, $name)
        : new BackupWasSuccessful(BackupDestination::create($disk, $name));
}

function makeFailedEvent(Throwable $exception, ?string $disk = null, ?string $name = null): BackupHasFailed
{
    if (spatieEventsUseStrings()) {
        return new BackupHasFailed($exception, $disk, $name);
    }

    return new BackupHasFailed($exception, $disk === null ? null : fakeDestination($disk, $name));
}

function makeHealthyEvent(string $disk, string $name): HealthyBackupWasFound
{
    return spatieEventsUseStrings()
        ? new HealthyBackupWasFound($disk, $name)
        : new HealthyBackupWasFound(fakeDestinationStatus($disk, $name));
}

/** @param  list<array{check: string, message: string}>  $failures */
function makeUnhealthyEvent(string $disk, string $name, array $failures): UnhealthyBackupWasFound
{
    if (spatieEventsUseStrings()) {
        return new UnhealthyBackupWasFound($disk, $name, collect($failures));
    }

    $status = fakeDestinationStatus($disk, $name);
    $failure = Mockery::mock(HealthCheckFailure::class);
    $failure->shouldReceive('exception')->andReturn(new Exception($failures[0]['message']));
    $status->shouldReceive('getHealthCheckFailure')->andReturn($failure);

    return new UnhealthyBackupWasFound($status);
}

function fakeDestination(string $disk, ?string $name): BackupDestination
{
    $destination = Mockery::mock(BackupDestination::class);
    $destination->shouldReceive('diskName')->andReturn($disk);
    $destination->shouldReceive('backupName')->andReturn($name);

    return $destination;
}

function fakeDestinationStatus(string $disk, string $name): BackupDestinationStatus
{
    $status = Mockery::mock(BackupDestinationStatus::class);
    $status->shouldReceive('backupDestination')->andReturn(fakeDestination($disk, $name));

    return $status;
}

/** The reason text the listener records differs by Spatie major. */
function expectedUnhealthyMessage(array $failures): string
{
    return spatieEventsUseStrings()
        ? collect($failures)->map(fn (array $f) => "[{$f['check']}] {$f['message']}")->implode("\n")
        : $failures[0]['message'];
}
