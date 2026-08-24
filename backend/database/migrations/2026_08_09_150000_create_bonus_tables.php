<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quỹ thưởng theo dự án.
 *
 * **Không có bảng phạt, và cố ý không có.** Điều 127 khoản 2 Bộ luật Lao động
 * 2019 nghiêm cấm "phạt tiền, cắt lương thay việc xử lý kỷ luật lao động" —
 * không phải chuyện đặt tên cho khéo: nếu tồn tại một bản ghi mang số tiền âm
 * trừ vào thu nhập vì làm sai thì bản chất là phạt tiền, dù cột đó tên gì.
 *
 * Thứ hợp pháp và giải quyết đúng nhu cầu là **thưởng có điều kiện** (Điều
 * 104): làm tốt thì phần chia lớn, làm kém thì phần chia nhỏ, kể cả bằng 0.
 * Không có "trừ" ở đâu cả.
 *
 * Kỷ luật lao động vẫn tồn tại nhưng luật chỉ cho bốn hình thức (Điều 124):
 * khiển trách, kéo dài thời hạn nâng lương, cách chức, sa thải. Không hình
 * thức nào là tiền, nên không hình thức nào thuộc về bảng này.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_bonus_pools', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            /*
            | Khoá ngoại tới `projects` ở tầng database, nhưng miền Payroll
            | KHÔNG khai quan hệ Eloquent tới model `Project`.
            |
            | deptrac chỉ cho `Payroll → Identity, Support`; muốn nói chuyện với
            | miền Task thì bắn Event, không gọi thẳng. Tầng Http là nơi duy
            | nhất biết cả hai miền và nó tự ghép tên dự án vào — cùng cách đã
            | dùng cho bảng lương ghép với `User`.
            */
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->decimal('total_amount', 15, 2);
            $table->char('currency', 3)->default('VND');

            /*
            | draft → locked → distributed, một chiều.
            |
            | Thiếu bước khoá thì con số thay đổi sau khi đã báo cho nhân viên,
            | và đó là thứ phá niềm tin nhanh nhất. Sau `locked` không sửa được
            | tổng quỹ lẫn phần chia.
            */
            $table->string('status', 20)->default('draft');

            // Điều kiện mở quỹ, ghi bằng lời: "dự án nghiệm thu đúng hạn",
            // "khách hàng thanh toán đủ". Không mã hoá thành luật máy chạy —
            // điều kiện thưởng là quyết định kinh doanh, không phải công thức.
            $table->string('condition_note', 500);

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('distributed_at')->nullable();

            $table->timestamps();

            // Một dự án một quỹ. Cần nhiều đợt thưởng thì mở rộng sau bằng cột
            // kỳ, không phải bằng cách cho phép hai quỹ trùng nhau ngay bây giờ.
            $table->unique('project_id');
        });

        Schema::create('bonus_allocations', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('pool_id')
                ->constrained('project_bonus_pools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 15, 2);

            // Bắt buộc. "Vì sao anh A được 5 triệu còn tôi 2 triệu" là câu chắc
            // chắn có người hỏi, và không ghi thì không ai trả lời được.
            $table->string('reason', 500);

            $table->foreignId('decided_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['pool_id', 'user_id']);
        });

        /*
        | Ràng buộc "không bao giờ âm", đặt ở DATABASE chứ không chỉ ở code.
        |
        | Đây là điều khiến hệ thống này không thể biến thành công cụ phạt tiền.
        | Kiểm ở tầng Action thì một Action mới viết sau này có thể quên; kiểm ở
        | database thì mọi đường ghi đều đâm vào cùng một bức tường, kể cả câu
        | UPDATE gõ tay trong tinker.
        |
        | MySQL thực thi CHECK từ 8.0.16; dự án chạy 8.4.
        */
        DB::statement(
            'ALTER TABLE bonus_allocations ADD CONSTRAINT chk_bonus_not_negative CHECK (amount >= 0)',
        );

        DB::statement(
            'ALTER TABLE project_bonus_pools ADD CONSTRAINT chk_pool_not_negative CHECK (total_amount >= 0)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_allocations');
        Schema::dropIfExists('project_bonus_pools');
    }
};
