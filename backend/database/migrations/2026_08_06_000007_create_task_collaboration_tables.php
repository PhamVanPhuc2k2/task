<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Các bảng vệ tinh quanh task: bình luận, người theo dõi, nhãn, nhật ký.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_comments', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            // Xoá cứng task thì bình luận đi theo — không để lại rác mồ côi.
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()
                ->constrained('task_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->text('body');

            // Bình luận đã sửa phải nhìn thấy được là đã sửa.
            $table->timestamp('edited_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['task_id', 'created_at']);
            $table->index('parent_id');
        });

        Schema::create('task_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });

        Schema::create('task_labels', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('name')->unique();
            // Mã màu hiển thị trên giao diện, dạng #RRGGBB.
            $table->string('color', 7)->default('#94a3b8');
            $table->timestamps();
        });

        Schema::create('task_task_label', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_label_id')->constrained()->cascadeOnDelete();

            $table->unique(['task_id', 'task_label_id']);
        });

        Schema::create('task_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();

            // Người gây ra thay đổi. nullOnDelete để nhật ký sống lâu hơn tài
            // khoản; nullable vì có thay đổi do hệ thống tự sinh (job quét hạn).
            $table->foreignId('causer_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('event', 50);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activities');
        Schema::dropIfExists('task_task_label');
        Schema::dropIfExists('task_labels');
        Schema::dropIfExists('task_user');
        Schema::dropIfExists('task_comments');
    }
};
