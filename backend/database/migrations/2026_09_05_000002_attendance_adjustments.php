<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đơn giải trình công — nhân viên tự nói ra vì sao một ngày đo thiếu.
 *
 * ## Lỗ hổng nó bịt
 *
 * `work_days` chỉ có một cửa vào: người quản lý bấm nút. Nhân viên đi gặp khách
 * cả ngày, mất mạng, hay quên mở máy thì không có đường nào nói điều đó **trong
 * hệ thống** — họ nhắn Zalo, quản lý nhớ thì bấm, quên thì thôi. Lý do thật của
 * một ngày công bất thường nằm trong lịch sử chat của hai người.
 *
 * Từ khi có chốt sổ kỳ công, chuyện đó thành hạn chót: sau khi kỳ chốt thì
 * không ai sửa được nữa, kể cả người quản lý muốn sửa. Nên nhân viên **phải** có
 * đường tự khởi xướng, và đường đó phải để lại vết.
 *
 * ## Đơn nằm ở đây, HẬU QUẢ nằm ở `work_days`
 *
 * Duyệt một đơn ghi một dòng `work_days` qua đúng `ReviewWorkDayAction` mà nút
 * bấm tay vẫn dùng. Một chỗ ghi, một hình dạng dữ liệu — bảng công không cần
 * biết con số đến từ nút bấm hay từ đơn.
 *
 * ## `approved_minutes` chép lại số đã duyệt, có chủ ý
 *
 * `work_days.adjusted_minutes` là số HIỆN HÀNH; người quản lý sửa thẳng ngày
 * công sau đó sẽ đổi nó. `approved_minutes` là số đã duyệt **trên đơn này**, và
 * không bao giờ đổi nữa. Đó là hai câu hỏi khác nhau, và gộp lại thì câu thứ
 * hai không còn trả lời được.
 *
 * ## Không có UNIQUE trên (user_id, work_date)
 *
 * Cùng lý do với `late_arrival_requests`: đơn bị từ chối không được chặn chỗ,
 * mà MySQL không có partial index. Ràng buộc thật — "không có hai đơn còn hiệu
 * lực cho cùng một ngày" — giữ ở tầng nghiệp vụ, trong giao dịch có khoá dòng,
 * và có test riêng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('work_date');
            $table->text('reason');

            /*
            | Số phút người nộp cho là đúng. NULL nghĩa là "xin bỏ qua ngày này,
            | tôi không đề nghị con số nào" — trường hợp thường gặp nhất, vì
            | người đi gặp khách cả ngày không đếm phút.
            |
            | Để NULL mang nghĩa đó thay vì bắt nhập 0: 0 phút là một lời khai
            | sai, và người duyệt sẽ đọc nó như một lời khai.
            */
            $table->unsignedSmallInteger('requested_minutes')->nullable();

            $table->string('status', 16)->default('pending');

            // Số người DUYỆT chốt — có thể khác số người nộp xin.
            $table->unsignedSmallInteger('approved_minutes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();

            // Màn "đơn của tôi", và câu kiểm trùng khi nộp đơn mới.
            $table->index(['user_id', 'work_date']);

            // Chặn chốt sổ khi kỳ còn đơn treo: đếm theo (trạng thái, ngày).
            $table->index(['status', 'work_date']);

            // Hộp duyệt của quản lý.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_adjustments');
    }
};
