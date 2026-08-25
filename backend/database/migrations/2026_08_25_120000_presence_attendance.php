<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chấm công theo **sự có mặt**, không còn theo thao tác.
 *
 * ── Vì sao đổi ───────────────────────────────────────────────────────────────
 *
 * Cách cũ chỉ ghi nhịp khi có thao tác thật trên Explus và tab đang hiển thị.
 * Với lập trình viên thì đó là đo sai người: họ sống trong IDE, terminal, trình
 * duyệt khác — cả buổi sáng viết code xong hệ thống hiện số 0.
 *
 * Đo hụt người làm thật tệ hơn hẳn so với đếm dư người treo máy: cái thứ nhất
 * làm người ta mất niềm tin vào bảng công, cái thứ hai nhìn dòng thời gian là
 * thấy.
 *
 * ── Nhưng không vứt tín hiệu đi ─────────────────────────────────────────────
 *
 * `interactive` giữ lại đúng thông tin mà cách cũ có: phút đó người dùng CÓ
 * bấm/gõ/cuộn hay chỉ để tab mở. Tổng giờ cộng cả hai, nhưng dòng thời gian vẫn
 * vẽ được hai loại khác màu.
 *
 * Không có cột này thì đổi cách tính đồng nghĩa với **mất hẳn** khả năng phân
 * biệt "ngồi làm" với "để đó" — và khi có tranh cãi về một ngày công cụ thể thì
 * không còn gì để nhìn.
 *
 * Mặc định `true` cho dòng cũ, và đó là giá trị ĐÚNG chứ không phải cho tiện:
 * mọi phiên ghi trước lần deploy này đều sinh ra từ thao tác thật.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_sessions', function (Blueprint $table): void {
            $table->boolean('interactive')->default(true)->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('work_sessions', function (Blueprint $table): void {
            $table->dropColumn('interactive');
        });
    }
};
