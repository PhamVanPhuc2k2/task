<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quỹ phép năm — phần con người can thiệp.
 *
 * ## Bảng thưa: không có dòng nghĩa là "để hệ thống tự tính"
 *
 * Cùng quy ước với `work_days`, `site_settings` và `attendance_periods`. Số
 * ngày phép của phần lớn nhân viên suy được từ ngày vào làm cộng thâm niên
 * (Điều 113 và 114 Bộ luật Lao động 2019), nên sinh sẵn một dòng cho mỗi người
 * mỗi năm là tạo ra một bảng vài nghìn dòng mà 95% chỉ chép lại phép tính.
 *
 * Tệ hơn: nó cần một job chạy đầu năm, và một năm thiếu dòng vì job không chạy
 * sẽ bị đọc thành "người này không có ngày phép nào".
 *
 * Bảng này chỉ ghi những gì **con người quyết định**: chuyển phép tồn, thưởng
 * thêm ngày, hoặc ghi đè hẳn con số.
 *
 * ## Vì sao `entitled_days_override` tách khỏi `adjustment_days`
 *
 * Hai ý định khác nhau. Ghi đè là *"đừng tính nữa, số đúng là 15"* — dùng cho
 * người có hợp đồng riêng. Điều chỉnh là *"số tính ra đúng rồi, cộng thêm 2 vì
 * dự án X"* — nó cộng dồn lên phép tính, nên năm sau thâm niên tăng thì vẫn
 * tăng theo.
 *
 * Gộp thành một cột thì mọi người từng được thưởng ngày phép sẽ kẹt cứng ở con
 * số của năm đó, và không ai nhận ra cho tới lúc có người hỏi vì sao thâm niên
 * không cộng.
 *
 * ## decimal(5,1) chứ không phải số nguyên
 *
 * Công ty làm sáng thứ bảy. Nghỉ một ngày thứ bảy là tiêu **nửa** ngày công,
 * nên mọi con số ở đây đều là bội của 0,5.
 *
 * ## Không lưu `used_days`
 *
 * Số ngày đã dùng suy từ `leave_requests` — nguồn sự thật duy nhất. Lưu thêm
 * một bản sao là mở đường cho hai con số lệch nhau sau lần đầu ai đó rút đơn,
 * và bản sao thì không có gì báo khi nó sai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');

            // NULL = dùng số tính tự động. Xem chú thích đầu tệp.
            $table->decimal('entitled_days_override', 5, 1)->nullable();

            $table->decimal('carried_over_days', 5, 1)->default(0);

            // Cộng dồn lên phép tính, và ĐƯỢC PHÉP ÂM: trừ bớt ngày phép cũng
            // là một quyết định có thật, và bắt nhân sự ghi đè cả con số chỉ để
            // trừ một ngày là làm mất luôn phần tính tự động.
            $table->decimal('adjustment_days', 5, 1)->default(0);

            $table->text('note')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Một người một năm một dòng. Đây là ràng buộc THẬT, khác với
            // `late_arrival_requests` nơi trạng thái làm nó thành có điều kiện.
            $table->unique(['user_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
