<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            // Nullable: việc vặt sếp giao trực tiếp không nhất thiết thuộc dự án.
            $table->foreignId('project_id')->nullable()
                ->constrained('projects')->nullOnDelete();
            $table->foreignId('parent_task_id')->nullable()
                ->constrained('tasks')->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // Toàn bộ để nullOnDelete: nhân viên nghỉ việc thì task vẫn còn
            // nguyên vết, chỉ trống ô người làm để quản lý bàn giao lại.
            $table->foreignId('assignee_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('assigner_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('status', 30)->default('todo');
            $table->string('priority', 30)->default('normal');

            $table->timestamp('due_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // DECIMAL chứ không phải FLOAT. Số giờ này chảy vào bảng lương ở
            // đợt 4; sai số nhị phân của float sẽ tích luỹ thành lỗi không giải
            // thích được với kế toán. Xem README "Quy ước dữ liệu".
            $table->decimal('estimate_hours', 6, 2)->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);

            // Đếm số lần dời hạn, hiển thị công khai trên chi tiết task.
            // Lịch sử chi tiết nằm ở bảng task_due_date_changes.
            $table->unsignedSmallInteger('due_date_change_count')->default(0);

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Index tổ hợp cho các truy vấn nóng — xem README mục 1.3.
            // "Task của tôi", lọc theo trạng thái, sắp theo hạn.
            $table->index(['assignee_id', 'status', 'due_date']);
            // Danh sách task trong một dự án.
            $table->index(['project_id', 'status']);
            // Job quét deadline chạy mỗi giờ, quét theo hạn trên toàn bảng.
            $table->index(['due_date', 'status']);
            $table->index('parent_task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
