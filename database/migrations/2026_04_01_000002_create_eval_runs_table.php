<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eval_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('dataset_id')
                ->constrained('eval_datasets')
                ->cascadeOnDelete();
            $table->string('dataset_name');
            $table->string('suite_class')->nullable();
            $table->string('subject_type');
            $table->string('subject_class')->nullable();
            $table->string('commit_sha')->nullable();
            $table->string('model')->nullable();
            $table->boolean('passed');
            $table->integer('pass_count');
            $table->integer('fail_count');
            $table->integer('error_count');
            $table->integer('total_count');
            $table->decimal('duration_ms', 10, 3);
            $table->decimal('total_cost_usd', 10, 6)->nullable();
            $table->integer('total_tokens_in')->nullable();
            $table->integer('total_tokens_out')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('dataset_id');
            $table->index(['passed', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eval_runs');
    }
};
