<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Đơn xin nghỉ.
 *
 * ## Phạm vi cố ý hẹp
 *
 * Bảng này **không** có quỹ phép, không có số ngày còn lại, không có nghỉ nửa
 * ngày. Ba thứ đó thuộc đợt 4 đầy đủ và đều cần công ty chốt chính sách trước:
 * một năm bao nhiêu ngày phép, phép tồn có chuyển sang năm sau không, nghỉ nửa
 * ngày tính công thế nào.
 *
 * Thứ làm ở đây là mảnh **gỡ được việc bấm tay hằng ngày**: nhân viên khai
 * mình nghỉ, quản lý duyệt một lần, và bảng công thôi hiện ô trống không rõ
 * nguyên nhân. Không cần biết quỹ phép cũng làm được việc đó.
 *
 * ## Vì sao lưu khoảng ngày, không lưu từng ngày
 *
 * Một đơn nghỉ ba ngày là **một** quyết định của quản lý, không phải ba. Tách
 * thành ba dòng thì duyệt được hai ngày và bỏ sót ngày thứ ba, và không còn
 * chỗ nào giữ lý do chung.
 *
 * Đổi lại, mọi truy vấn "ngày X có nghỉ không" thành so sánh khoảng — nên có
 * index theo `(status, start_date, end_date)`.
 *
 * ## Cột ngày là DATE theo giờ Việt Nam
 *
 * Cùng quy ước với `work_date` và `report_date`: ngày nghỉ là **ngày trên
 * lịch**, không phải một mốc thời gian. Lưu TIMESTAMP rồi suy ra ngày là mở
 * lại đúng cái bẫy múi giờ mà App\Support\Time\WorkDate sinh ra để chặn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 32);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');

            $table->string('status', 16)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();

            // Màn "đơn của tôi": mọi đơn của một người, mới nhất trước.
            $table->index(['user_id', 'start_date']);

            // Câu hỏi nóng nhất của cả bảng: "những ngày nghỉ đã duyệt trong
            // khoảng này là của ai". Bảng công tháng gọi nó một lần cho cả
            // phòng, mỗi lần mở trang.
            $table->index(['status', 'start_date', 'end_date']);

            // Hộp duyệt của quản lý.
            $table->index(['status', 'created_at']);
        });

        /*
         * Ngày kết thúc không được trước ngày bắt đầu.
         *
         * Ràng buộc ở tầng database chứ không chỉ ở tầng validate: đơn nghỉ
         * "từ 20/08 đến 15/08" làm mọi phép so sánh khoảng trả về rỗng, nên nó
         * hỏng theo kiểu im lặng — ngày nghỉ đơn giản là không bao giờ khớp,
         * và không có gì báo.
         */
        DB::statement(
            'ALTER TABLE leave_requests
             ADD CONSTRAINT chk_leave_dates CHECK (end_date >= start_date)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
