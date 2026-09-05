<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đăng ký làm thêm giờ — duyệt TRƯỚC mới được tính.
 *
 * ## Vì sao phải đăng ký trước
 *
 * Làm thêm giờ ra tiền ở mức 150–300% (Điều 98 Bộ luật Lao động 2019). Suy nó
 * từ giờ ngồi trước máy là để hệ thống tự ký một khoản chi mà không ai quyết
 * định — và một cái tab quên đóng qua đêm sẽ thành mười tiếng làm thêm ngày
 * nghỉ. Đó cũng là lý do có `max_daily_minutes`.
 *
 * Nên đây là một ĐƠN: nhân viên đăng ký mốc giờ và lý do, quản lý duyệt, và chỉ
 * phần đã duyệt mới đi vào bảng lương.
 *
 * ## `rate_percent` chốt lúc DUYỆT, không phải lúc đăng ký
 *
 * Hệ số suy từ loại ngày — thường, nghỉ tuần, hay lễ. Loại ngày có thể đổi sau
 * khi đơn đã nộp: nhân sự nhập thêm một ngày lễ, hoặc công ty đổi lịch tuần.
 * Lúc đăng ký thì màn hình tính sống để người nộp biết mình sắp được trả bao
 * nhiêu; lúc duyệt thì con số được đóng băng, vì đó là thời điểm công ty cam
 * kết trả.
 *
 * Ghi bằng PHẦN TRĂM NGUYÊN (150, 200, 300) chứ không phải hệ số thập phân —
 * xem `OvertimePolicy`.
 *
 * ## Không có UNIQUE trên (user_id, work_date)
 *
 * Một người làm thêm hai lần trong ngày là chuyện có thật: sáng sớm một tiếng,
 * tối hai tiếng. Ràng buộc thật là **không có hai khoảng giờ CHỒNG LẤN nhau**,
 * mà điều đó không diễn đạt được bằng một index. Nó nằm ở tầng nghiệp vụ, trong
 * giao dịch có khoá dòng, và có test riêng.
 *
 * ## Chưa có: nghỉ bù, và phụ cấp làm đêm
 *
 * Điều 98 khoản 3 cho phép thoả thuận nghỉ bù thay vì trả tiền, và khoản 2–3
 * cộng thêm 30%/20% cho giờ làm ban đêm. Công ty hiện không có ca đêm và chưa
 * chốt chính sách nghỉ bù. Khi có thì thêm cột ở đây; các đơn cũ giữ nguyên
 * nghĩa vì `rate_percent` đã đóng băng con số của lúc duyệt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('work_date');

            /*
            | Giờ Việt Nam, cột TIME không có múi giờ — cùng quy ước với
            | `late_arrival_requests.expected_arrival`. "20h tới 22h" là con số
            | người ta nói với nhau, không phải một mốc trên trục thời gian.
            */
            $table->time('start_time');
            $table->time('end_time');

            /*
            | Số phút suy ra từ hai mốc trên, lưu lại chứ không tính mỗi lần.
            |
            | Ba cái trần của Điều 107 đều là phép SUM theo ngày, tháng, năm.
            | Tính trong PHP thì phải nạp mọi đơn của cả năm về bộ nhớ chỉ để
            | cộng vài con số — đúng thứ đã tránh ở `SummariseAttendanceAction`.
            */
            $table->unsignedSmallInteger('minutes');

            $table->text('reason');

            $table->string('status', 16)->default('pending');

            // Đóng băng lúc duyệt. NULL nghĩa là chưa ai duyệt.
            $table->unsignedSmallInteger('rate_percent')->nullable();

            // Số phút NGƯỜI DUYỆT chốt — có thể ít hơn số đã đăng ký.
            $table->unsignedSmallInteger('approved_minutes')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();

            // Màn "đơn của tôi", phép kiểm chồng lấn, và ba cái trần theo ngày.
            $table->index(['user_id', 'work_date']);

            // Chặn chốt sổ khi kỳ còn đơn treo; và bảng tổng hợp giờ làm thêm
            // của một kỳ ở chặng tính lương.
            $table->index(['status', 'work_date']);

            // Hộp duyệt của quản lý.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
