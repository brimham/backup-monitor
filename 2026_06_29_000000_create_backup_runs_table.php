<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();

            // What kind of event produced this row, and how it turned out.
            $table->string('type')->default('backup');   // backup | health_check | cleanup
            $table->string('status');                     // success | failed | healthy | unhealthy

            // Which backup + destination this relates to.
            $table->string('backup_name')->nullable();    // config('backup.backup.name')
            $table->string('disk')->nullable();           // destination disk, e.g. "s3", "local"

            // Outcome detail.
            $table->unsignedBigInteger('size_in_bytes')->nullable();
            $table->string('path')->nullable();           // path of the newest backup at the destination
            $table->text('message')->nullable();          // failure reason / exception message
            $table->json('meta')->nullable();             // room to grow: duration, trigger, tenant id, etc.

            $table->timestamps();

            // The queries the dashboard will actually run.
            $table->index(['disk', 'status']);
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
