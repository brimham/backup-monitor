<?php

namespace Brimham\BackupMonitor\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BackupRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'size_in_bytes' => 'integer',
        'meta' => 'array',
    ];

    public function scopeBackups(Builder $query): Builder
    {
        return $query->where('type', 'backup');
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function wasSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * The most recent backup run for each destination disk.
     * Powers the "last backup per destination" health view in the free panel.
     *
     * Note: fine for modest history sizes. Once the table grows large,
     * swap this for a windowed/grouped SQL query (e.g. a lateral join or
     * a subquery selecting MAX(created_at) per disk) so you are not pulling
     * every row into PHP to dedupe.
     */
    public static function latestPerDisk(): Collection
    {
        return static::query()
            ->backups()
            ->orderByDesc('created_at')
            ->get()
            ->unique('disk')
            ->values();
    }
}
