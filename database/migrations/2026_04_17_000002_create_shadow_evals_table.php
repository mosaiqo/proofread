<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shadow_evals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('capture_id')
                ->constrained('shadow_captures')
                ->cascadeOnDelete();
            $table->string('agent_class');
            $table->boolean('passed');
            $table->integer('total_assertions');
            $table->integer('passed_assertions');
            $table->integer('failed_assertions');
            $table->json('assertion_results');
            $table->decimal('judge_cost_usd', 10, 6)->nullable();
            $table->integer('judge_tokens_in')->nullable();
            $table->integer('judge_tokens_out')->nullable();
            $table->decimal('evaluation_duration_ms', 10, 3);
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->index('capture_id');
            $table->index('agent_class');
            $table->index(['agent_class', 'evaluated_at']);
            $table->index(['passed', 'evaluated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shadow_evals');
    }
};
