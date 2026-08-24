<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chấm công — đợt 3.
 *
 * Không có bảng `attendances` với cặp bấm vào/ra như bản kế hoạch đầu. Công ty
 * làm remote: nút bấm tay thì người ta quên, và giờ "treo tab" không nói lên
 * điều gì. Thay vào đó hệ thống suy ra **phiên làm việc** từ tương tác thật với
 * ứng dụng, còn con người quyết định ngày công có được ghi nhận hay không.
 *
 * Ba bảng, không hơn. `work_shifts` và `attendance_policies` trong kế hoạch cũ
 * để lại: giờ giấc đang linh hoạt nên "đi muộn" chưa có nghĩa, và một chính
 * sách duy nhất thì chưa cần bảng chính sách.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        | Phiên làm việc liên tục, suy ra từ nhịp tim của giao diện.
        |
        | KHÔNG lưu từng nhịp tim. Một nhịp mỗi phút, tám tiếng một ngày, hai
        | trăm nhân sự, hai mươi hai ngày công là khoảng 2,1 triệu dòng mỗi
        | tháng cho một thông tin không ai đọc tới. Nhịp tim tới thì nối dài
        | `ended_at` của phiên đang mở; cách phiên gần nhất quá ngưỡng thì mở
        | phiên mới. Kết quả còn 3–6 dòng mỗi người mỗi ngày.
        */
        Schema::create('work_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamp('started_at');
            $table->timestamp('ended_at');

            /*
            | Ngày công, tính theo GIỜ VIỆT NAM, lưu thành cột riêng.
            |
            | Đây là chỗ chặn cái bẫy đắt nhất của mọi hệ thống chấm công. Giờ
            | lưu ở UTC (xem README, "Quy ước dữ liệu"), nên người làm tới 00:30
            | giờ Việt Nam có `started_at` rơi vào ngày UTC HÔM TRƯỚC. Gom bảng
            | công bằng `DATE(started_at)` là lệch ngày, và lệch âm thầm — dữ
            | liệu vẫn lưu được, vẫn đọc ra được, chỉ sai ngày.
            |
            | Tính một lần lúc ghi rồi lưu lại, không tính lại mỗi lần đọc.
            */
            $table->date('work_date');

            // 'heartbeat' — hệ thống tự ghi. 'manual' — người dùng tự khai cho
            // quãng làm việc không sinh dấu vết (họp cả buổi, đi gặp khách).
            $table->string('source', 20)->default('heartbeat');

            $table->timestamps();

            $table->index(['user_id', 'work_date']);
            // Bảng tháng của cả phòng quét theo ngày trước, lọc người sau.
            $table->index(['work_date', 'user_id']);
        });

        /*
        | Ngày công có can thiệp của con người.
        |
        | Bảng THƯA: ngày bình thường không sinh dòng nào, số giờ suy thẳng từ
        | `work_sessions`. Chỉ ngày nào có người quyết định — ghi nhận, bỏ qua,
        | hoặc chỉnh tay — mới có bản ghi. Cùng nguyên tắc với
        | `user_notification_settings` ở mục 1.6: không có dòng nghĩa là dùng
        | giá trị mặc định.
        |
        | Đây là phần kiểm toán thật sự cần. "Duyệt không trừ tuỳ hoàn cảnh"
        | chính là loại quyết định sinh tranh cãi sáu tháng sau — "sao tháng
        | trước anh bỏ qua cho tôi mà tháng này lại tính?" — và không ghi ai
        | quyết định, vì sao, thì không ai trả lời được.
        */
        Schema::create('work_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');

            $table->string('decision', 20);

            // Số phút do người duyệt ấn định, ghi đè số suy ra từ phiên. Null
            // nghĩa là giữ nguyên số hệ thống đo được.
            $table->unsignedSmallInteger('adjusted_minutes')->nullable();

            // BẮT BUỘC có lý do. Một quyết định không lý do thì sáu tháng sau
            // vô dụng ngang với không ghi gì.
            $table->string('reason', 500);

            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at');

            $table->timestamps();

            // Một người một ngày chỉ có một quyết định hiện hành.
            $table->unique(['user_id', 'work_date']);
        });

        /*
        | Ngày nghỉ lễ.
        |
        | Không hardcode: Tết âm lịch trôi theo năm dương lịch, và Điều 112
        | Bộ luật Lao động 2019 cho nghỉ bù khi ngày lễ trùng ngày nghỉ hằng
        | tuần — nên ngày thực nghỉ khác ngày lễ trên giấy. `observed_date` giữ
        | ngày thực nghỉ, `date` giữ ngày lễ gốc.
        */
        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->date('observed_date');
            $table->string('name');
            $table->boolean('is_paid')->default(true);
            $table->timestamps();

            $table->index('observed_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('work_days');
        Schema::dropIfExists('work_sessions');
    }
};
