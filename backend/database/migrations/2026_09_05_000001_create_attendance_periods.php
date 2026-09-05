<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kỳ công đã chốt sổ.
 *
 * Tên file NGẮN theo quy ước — xem README, "Larastan sập khi thêm migration".
 *
 * ## Bảng thưa: chỉ có dòng cho kỳ đã từng bị chốt
 *
 * Kỳ chưa ai động tới thì không có dòng, và mặc định là mở. Sinh sẵn một dòng
 * cho mọi tháng buộc phải có một job sinh dòng, và một tháng thiếu dòng vì job
 * không chạy sẽ bị coi là "không tồn tại" thay vì "đang mở" — hỏng im lặng.
 * Cùng quy ước với `work_days` và `site_settings`.
 *
 * ## Cột "mở khoá" giữ lần GẦN NHẤT, không giữ lịch sử
 *
 * Một kỳ có thể chốt → mở → chốt lại nhiều lần. Bảng này trả lời "hiện giờ kỳ
 * đó thế nào"; còn "đã đóng mở bao nhiêu lần, ai làm, vì sao" nằm ở
 * `payroll_audits` — nhật ký chỉ ghi thêm, không sửa, không xoá.
 *
 * Tách hai vai trò như vậy vì chúng được đọc ở hai nhịp khác nhau: trạng thái
 * hiện tại bị hỏi ở **mọi** request ghi số liệu, còn lịch sử chỉ được đọc khi
 * có người đi tìm câu trả lời.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_periods', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            /*
            | Kỳ công dạng `YYYY-MM`, theo tháng dương lịch — công ty đã chốt.
            |
            | Là CHUỖI chứ không phải cột DATE, cùng quy ước với `work_date` và
            | `report_date`: đây là một nhãn kỳ, không phải một mốc trên trục
            | thời gian. Cast sang Carbon sẽ gắn ngày 1 và múi giờ UTC, rồi mở
            | lại đúng cái bẫy lệch ngày mà cột kiểu DATE sinh ra để chặn.
            |
            | `unique` là ràng buộc thật: hai dòng cho cùng một kỳ thì câu hỏi
            | "tháng 9 đã chốt chưa" hết đáp án duy nhất.
            */
            $table->char('period', 7)->unique();

            $table->string('status', 16);

            $table->timestamp('closed_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();

            // Lý do BẮT BUỘC khi mở khoá — ràng buộc đó nằm ở Action, không ở
            // đây, vì cột này rỗng với kỳ chưa từng mở lại.
            $table->text('reopen_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_periods');
    }
};
