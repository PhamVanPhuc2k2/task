<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng thông báo chuẩn của Laravel, cộng thêm index cho truy vấn nóng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Chuông đếm số chưa đọc trên mọi lần tải trang — không có index này
            // thì mỗi lần mở app là một lần quét toàn bảng.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        Schema::create('user_notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Lưu chuỗi của NotificationType, không lưu số — đọc dump database
            // vẫn hiểu, và thêm loại mới không phải migrate.
            $table->string('type', 60);

            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(false);

            $table->timestamps();

            // Chỉ ghi dòng cho những loại người dùng đã tự chỉnh. Không có dòng
            // nghĩa là dùng mặc định của NotificationType — thêm loại thông báo
            // mới về sau không cần backfill cho toàn bộ nhân sự.
            $table->unique(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_settings');
        Schema::dropIfExists('notifications');
    }
};
