<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cài đặt trang, dạng key/value.
 *
 * ## Vì sao key/value chứ không phải mỗi cài đặt một cột
 *
 * Thêm một cài đặt mới thì chỉ thêm một `case` vào `SettingKey`, không cần
 * migration. Với dự án này đó là lý do rất thật, không phải sở thích: bộ quét
 * migration của Larastan đang nằm sát ngưỡng và mỗi migration mới là một lần
 * đánh cược (xem README, "Larastan sập khi thêm migration").
 *
 * Cái giá của key/value thường là mất kiểu dữ liệu — ở đây trả bằng `SettingKey`
 * và `SettingType`: khoá là enum nên không gõ bừa được, kiểu khai trong mã nên
 * `get()` luôn trả về đúng `int`/`bool`/`string`.
 *
 * ## Không có cột `type`
 *
 * Kiểu thuộc về **mã**, không thuộc về dữ liệu. Lưu kiểu trong database thì có
 * hai nguồn sự thật, và ngày chúng lệch nhau thì không ai biết bên nào đúng.
 *
 * ## Dòng vắng mặt nghĩa là "dùng mặc định"
 *
 * Xoá dòng là quay về mặc định trong config, không phải đặt về null. Nhờ vậy
 * sau này đổi mặc định trong config vẫn có tác dụng với người chưa từng chỉnh —
 * còn nếu ghi cứng giá trị cũ vào database thì nó đứng đó mãi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();

            // 64 ký tự: khoá dài nhất hiện tại là `report_reminder_enabled`
            // (23), còn rất nhiều chỗ. Unique vì mỗi khoá đúng một dòng.
            $table->string('key', 64)->unique();

            // `text` nullable: đủ cho đường dẫn logo, và null là hợp lệ (logo
            // chưa đặt). Không dùng json — giá trị luôn là một đại lượng đơn.
            $table->text('value')->nullable();

            // Ai đổi lần cuối. Cài đặt là thứ đổi cả cách tính công của công
            // ty, nên phải trả lời được "ai bấm nút này".
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
