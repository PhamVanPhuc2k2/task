<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhật ký thay đổi hồ sơ nhân sự.
 *
 * Cùng hình dạng với `task_activities` (mục 1.4) và cùng lý do: đổi phòng ban,
 * đổi vai trò, cho nghỉ việc đều là những việc mà sáu tháng sau sẽ có người
 * hỏi "ai làm, lúc nào". Không ghi thì câu trả lời duy nhất là "không biết".
 *
 * Đây là loại dữ liệu mà chính người có quyền quản trị nhân sự cũng không được
 * sửa — nên bảng chỉ ghi thêm, không cột `updated_at` nào có ý nghĩa và không
 * có soft delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activities', function (Blueprint $table): void {
            $table->id();

            // Người BỊ thay đổi. cascadeOnDelete theo bản ghi người dùng: nếu
            // một ngày thật sự xoá cứng một tài khoản (yêu cầu xoá dữ liệu cá
            // nhân theo Nghị định 13) thì nhật ký về người đó cũng phải đi theo.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Người GÂY RA thay đổi. nullOnDelete để nhật ký sống lâu hơn tài
            // khoản người thao tác; nullable vì có thay đổi do lệnh dòng lệnh
            // (`users:import`) sinh ra, không có ai đứng sau.
            $table->foreignId('causer_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('event', 50);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activities');
    }
};
