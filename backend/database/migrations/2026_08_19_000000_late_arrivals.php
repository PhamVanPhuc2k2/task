<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đơn xin đi làm muộn.
 *
 * ## Vì sao là bảng riêng chứ không phải một loại nghỉ phép
 *
 * `leave_requests` đo bằng NGÀY — `start_date` và `end_date` đều là cột DATE.
 * Đơn này đo bằng GIỜ: "mai tôi tới lúc 9h30". Nhét vào bảng kia thì phải thêm
 * cột giờ mà 99% số dòng để trống, và mọi truy vấn khoảng ngày phải thêm một
 * nhánh "trừ loại này ra".
 *
 * Nhưng nó vẫn nằm trong **miền Leave**, không phải miền Attendance: đây là
 * một đơn có người duyệt, dùng chung `LeaveStatus`, chung quyền `leave.approve`,
 * chung người duyệt và chung màn hình. Miền Attendance chỉ *đọc* kết quả.
 *
 * ## `expected_arrival` là giờ Việt Nam
 *
 * Cột TIME, không có múi giờ, và cố ý như vậy: "9h30" là con số người ta nói
 * với nhau, không phải một mốc trên trục thời gian. Ghép với ngày và quy sang
 * UTC là việc của lúc so sánh — xem App\Domain\Attendance\Data\WorkShift.
 *
 * ## Không có ràng buộc UNIQUE trên (user_id, date)
 *
 * Vì đơn bị từ chối hoặc đã rút KHÔNG được chặn chỗ: bị từ chối rồi nộp lại
 * với lý do rõ hơn là chuyện bình thường. UNIQUE đầy đủ sẽ cấm luôn việc đó.
 * Ràng buộc thật — "không có hai đơn còn hiệu lực cho cùng một ngày" — là điều
 * kiện có lọc theo trạng thái, mà MySQL không có partial index. Nó được giữ ở
 * tầng validate, và có test riêng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('late_arrival_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('date');
            $table->time('expected_arrival');
            $table->text('reason');

            $table->string('status', 16)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();

            // Màn "đơn của tôi", và cũng là câu kiểm trùng khi nộp đơn mới.
            $table->index(['user_id', 'date']);

            // Câu hỏi nóng nhất: "những ngày đã duyệt cho đi muộn trong khoảng
            // này là của ai". Bảng công tháng gọi nó một lần cho cả phòng, mỗi
            // lần mở trang.
            $table->index(['status', 'date']);

            // Hộp duyệt của quản lý.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_arrival_requests');
    }
};
