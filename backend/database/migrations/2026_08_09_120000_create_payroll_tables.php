<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mức lương — đợt 5, phần đặt và xem.
 *
 * **Không nằm trên bảng `users`, có chủ ý.** `users` chảy ra ngoài qua
 * `UserResource` ở `/auth/me`, `/users`, và lồng trong mọi task có người thực
 * hiện. Một cột lương ở đó sẽ lọt ra qua bất kỳ `toArray()` nào — một `dd()`
 * lúc gỡ lỗi, một payload job vào Redis, một dòng log của thư viện bên thứ ba.
 * Dự án đã dính đúng họ lỗi này với `two_factor_secret` (mục 1.9) và phải chặn
 * bằng `#[Hidden]`; với lương thì lớp phòng vệ đó quá mỏng. Bảng riêng nghĩa là
 * phải CỐ Ý truy vấn mới đọc được.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        | Lương là LỊCH SỬ, không phải một con số hiện tại.
        |
        | Đây là cái bẫy mà nhiều hệ thống mắc: lưu `salary` như giá trị hiện
        | hành, rồi tháng 6 tăng lương, tháng 7 kế toán tính lại bảng lương
        | tháng 3 và ra số sai — vì hệ thống chỉ còn biết mức mới.
        |
        | Tăng lương là THÊM một dòng và đóng dòng cũ, không phải `UPDATE`.
        */
        Schema::create('salary_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('effective_from');
            // null = đang hiệu lực. Mỗi người chỉ có tối đa một dòng như vậy.
            $table->date('effective_to')->nullable();

            /*
            | DECIMAL chứ không FLOAT. Quy ước đã chốt ở mục 1.3 và nhắc lại ở
            | migration `tasks`: float không biểu diễn chính xác được số thập
            | phân, và sai số làm tròn trên tiền lương là thứ kế toán sẽ phát
            | hiện ra bằng cách cộng tay rồi hỏi vì sao lệch.
            |
            | 15 chữ số đủ cho hàng nghìn tỉ đồng.
            */
            $table->decimal('base_salary', 15, 2);
            $table->decimal('allowance', 15, 2)->default(0);

            /*
            | Luôn có đơn vị tiền tệ, kể cả khi công ty chỉ trả VND.
            |
            | Một cột mặc định 'VND' gần như không tốn gì; còn số tiền không có
            | đơn vị thì tới ngày công ty trả cho một cộng tác viên nước ngoài
            | là dữ liệu hỏng không khôi phục được — không cách nào biết dòng
            | nào là đồng nào.
            */
            $table->char('currency', 3)->default('VND');

            // Bắt buộc. Cùng lý do với lý do duyệt ngày công ở đợt 3: sáu tháng
            // sau sẽ có người hỏi vì sao mức này thay đổi.
            $table->string('reason', 500);

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'effective_from']);
        });

        /*
        | Nhật ký lương — ghi cả việc XEM, không chỉ việc sửa.
        |
        | Khác với mọi bảng nhật ký khác trong hệ thống. Với lương, câu hỏi "ai
        | đã xem bảng lương phòng Kinh doanh" là câu có thật và sẽ có người hỏi.
        |
        | Bảng riêng của miền Payroll chứ không dùng `user_activities` của
        | Identity: enum biến cố bên đó không nên phình ra vì khái niệm của miền
        | khác, và nhật ký lương có vòng đời lưu trữ riêng.
        */
        Schema::create('payroll_audits', function (Blueprint $table): void {
            $table->id();

            $table->string('event', 30);

            $table->foreignId('actor_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // Null khi biến cố là xem cả bảng lương, không nhắm vào ai cụ thể.
            $table->foreignId('subject_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // KHÔNG chứa số tiền. Nhật ký kiểm toán mà mang theo dữ liệu nhạy
            // cảm thì bản thân nó thành chỗ rò rỉ thứ hai — cùng lý do đã ghi
            // ở nhật ký đặt lại mật khẩu.
            $table->json('context')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['subject_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_audits');
        Schema::dropIfExists('salary_records');
    }
};
