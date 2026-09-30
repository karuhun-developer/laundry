<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('status', 20)->default('waiting');
            $table->string('operator_name')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->text('address')->nullable();
            $table->string('recipient_name')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'kind', 'status']);
        });
        Schema::create('order_complaints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 20);
            $table->text('description');
            $table->string('status', 20)->default('new');
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });
        Schema::create('order_operation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_task_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('order_complaint_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name_snapshot');
            $table->string('status', 20);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_operation_events');
        Schema::dropIfExists('order_complaints');
        Schema::dropIfExists('order_tasks');
    }
};
