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
            $table->foreignUlid('comparison_id')
                ->nullable()
                ->after('dataset_version_id')
                ->constrained('eval_comparisons')
                ->nullOnDelete();
            $table->string('subject_label')->nullable()->after('subject_class');

            $table->index('comparison_id');
        });
    }

    public function down(): void
    {
        Schema::table('eval_runs', function (Blueprint $table): void {
            $table->dropForeign(['comparison_id']);
        });

        Schema::table('eval_runs', function (Blueprint $table): void {
            $table->dropIndex(['comparison_id']);
        });

        Schema::table('eval_runs', function (Blueprint $table): void {
            $table->dropColumn(['comparison_id', 'subject_label']);
        });
    }
};
