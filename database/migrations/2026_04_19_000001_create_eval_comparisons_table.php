<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eval_comparisons', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('suite_class')->nullable();
            $table->string('dataset_name');
            $table->foreignUlid('dataset_version_id')
                ->nullable()
                ->constrained('eval_dataset_versions')
                ->nullOnDelete();
            $table->json('subject_labels');
            $table->string('commit_sha')->nullable();
            $table->integer('total_runs');
            $table->integer('passed_runs');
            $table->integer('failed_runs');
            $table->decimal('total_cost_usd', 10, 6)->nullable();
            $table->decimal('duration_ms', 10, 3);
            $table->timestamps();

            $table->index(['dataset_name', 'created_at']);
            $table->index(['suite_class', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eval_comparisons');
    }
};
