<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eval_runs', function (Blueprint $table): void {
            $table->foreignUlid('dataset_version_id')
                ->nullable()
                ->after('dataset_id')
                ->constrained('eval_dataset_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('eval_runs', function (Blueprint $table): void {
            $table->dropForeign(['dataset_version_id']);
            $table->dropColumn('dataset_version_id');
        });
    }
};
