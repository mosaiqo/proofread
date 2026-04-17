<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eval_dataset_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('eval_dataset_id')
                ->constrained('eval_datasets')
                ->cascadeOnDelete();
            $table->string('checksum', 64);
            $table->json('cases');
            $table->integer('case_count');
            $table->timestamp('first_seen_at');
            $table->timestamps();

            $table->index('eval_dataset_id');
            $table->unique(['eval_dataset_id', 'checksum']);
            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eval_dataset_versions');
    }
};
