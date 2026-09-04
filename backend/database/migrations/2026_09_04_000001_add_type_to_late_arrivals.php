<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cho bảng đơn đi muộn giữ thêm đơn XIN VỀ SỚM.
 *
 * Tên file cố tình NGẮN — bỏ tiền tố `create_` và hậu tố `_table`. Bộ quét
 * migration của Larastan đang nằm sát ngưỡng độ dài đường dẫn; xem README mục
 * "Larastan sập khi thêm migration".
 *
 * ## Ba thay đổi, và vì sao cột giờ phải tách đôi
 *
 * "Tôi sẽ tới lúc 9h30" và "tôi sẽ về lúc 16h" không phải cùng một dữ liệu.
 * Nhét chung một cột thì tên cột nói dối một nửa số dòng, và mọi chỗ đọc nó
 * phải kiểm `type` trước mới hiểu con số nghĩa là gì.
 *
 * Nên `expected_arrival` được nới thành nullable (đơn về sớm để trống), và
 * `expected_departure` là cột mới. Ràng buộc "đúng một trong hai, khớp loại"
 * đặt ở **database** chứ không chỉ ở validate — cùng lý do với ràng buộc không
 * âm của quỹ thưởng: một đường ghi mới sau này (lệnh nhập liệu, job đồng bộ) đi
 * vòng qua tầng ứng dụng là đi vòng qua luôn mọi phép kiểm ở đó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('late_arrival_requests', function (Blueprint $table): void {
            $table->string('type', 16)->default('late')->after('date');
            $table->time('expected_departure')->nullable()->after('expected_arrival');
        });

        // Nới NOT NULL thành nullable. Tách khỏi khối trên vì đổi kiểu cột cần
        // doctrine/dbal ở một số phiên bản, và gộp chung thì hỏng cả lô.
        Schema::table('late_arrival_requests', function (Blueprint $table): void {
            $table->time('expected_arrival')->nullable()->change();
        });

        /*
        | Đúng một trong hai cột giờ được điền, và phải khớp `type`.
        |
        | Viết bằng SQL thô vì Blueprint chưa có API cho CHECK nhiều điều kiện.
        | Đặt tên ràng buộc tường minh để `down()` gỡ được đúng nó.
        */
        DB::statement(<<<'SQL'
            ALTER TABLE late_arrival_requests
            ADD CONSTRAINT chk_late_arrival_time_matches_type CHECK (
                (type = 'late'  AND expected_arrival   IS NOT NULL AND expected_departure IS NULL)
                OR
                (type = 'early' AND expected_departure IS NOT NULL AND expected_arrival   IS NULL)
            )
        SQL);

        Schema::table('late_arrival_requests', function (Blueprint $table): void {
            // Hạn mức và phép kiểm trùng đều lọc theo (người, ngày, loại): một
            // người có thể vừa xin đi muộn vừa xin về sớm trong cùng một ngày.
            $table->index(['user_id', 'date', 'type']);
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE late_arrival_requests DROP CHECK chk_late_arrival_time_matches_type');

        Schema::table('late_arrival_requests', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'date', 'type']);
            $table->dropColumn(['type', 'expected_departure']);
        });
    }
};
