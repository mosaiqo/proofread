<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shadow_captures', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('agent_class');
            $table->string('prompt_hash', 64);
            $table->json('input_payload');
            $table->longText('output')->nullable();
            $table->integer('tokens_in')->nullable();
            $table->integer('tokens_out')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->decimal('latency_ms', 10, 3)->nullable();
            $table->string('model_used')->nullable();
            $table->timestamp('captured_at');
            $table->decimal('sample_rate', 5, 4);
            $table->boolean('is_anonymized')->default(true);
            $table->timestamps();

            $table->index('agent_class');
            $table->index(['captured_at']);
            $table->index(['agent_class', 'captured_at']);
            $table->index('prompt_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shadow_captures');
    }
};
