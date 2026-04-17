<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eval_results', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('run_id')
                ->constrained('eval_runs')
                ->cascadeOnDelete();
            $table->integer('case_index');
            $table->string('case_name')->nullable();
            $table->json('input');
            $table->text('output')->nullable();
            $table->json('expected')->nullable();
            $table->boolean('passed');
            $table->json('assertion_results');
            $table->string('error_class')->nullable();
            $table->text('error_message')->nullable();
            $table->longText('error_trace')->nullable();
            $table->decimal('duration_ms', 10, 3);
            $table->decimal('latency_ms', 10, 3)->nullable();
            $table->integer('tokens_in')->nullable();
            $table->integer('tokens_out')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->string('model')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['run_id', 'case_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eval_results');
    }
};
