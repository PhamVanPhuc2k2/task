<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dấu vết đã nhắc deadline.
 *
 * Job quét chạy mỗi giờ trong giờ hành chính. Không có hai cột này thì mỗi lượt
 * quét lại gửi tiếp một thông báo cho cùng một task — chín lần một ngày, và
 * người dùng sẽ tắt thông báo trong ngày đầu tiên.
 *
 * Hai cột này bị xoá về null khi task được dời hạn, để hạn mới được nhắc lại.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->timestamp('due_soon_notified_at')->nullable()->after('due_date_change_count');
            $table->timestamp('overdue_notified_at')->nullable()->after('due_soon_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn(['due_soon_notified_at', 'overdue_notified_at']);
        });
    }
};
