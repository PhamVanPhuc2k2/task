<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lịch sử dời hạn.
 *
 * Toàn bộ việc đánh giá đúng hạn ở đợt 5 dựa trên deadline. Nếu ai cũng tự dời
 * hạn khi sắp trễ thì mọi chỉ số về sau đều vô nghĩa — nên mỗi lần dời đều phải
 * để lại vết, và `reason` là NOT NULL ở tầng database chứ không chỉ ở tầng ứng
 * dụng, để không ai lách được bằng cách ghi thẳng vào bảng.
 *
 * Bảng này chỉ ghi thêm, không sửa, không xoá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_due_date_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();

            $table->timestamp('old_due_date')->nullable();
            $table->timestamp('new_due_date')->nullable();

            $table->text('reason');

            $table->foreignId('requested_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_due_date_changes');
    }
};
