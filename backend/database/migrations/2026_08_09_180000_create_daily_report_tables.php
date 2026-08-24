<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Báo cáo tiến độ hằng ngày — đợt 2.
 *
 * **Một báo cáo mỗi người mỗi ngày**, không phải một bản ghi mỗi task. Cách
 * này trả lời được câu mà chấm công cần: *"hôm nay ai chưa báo cáo"* — gắn báo
 * cáo vào từng task thì câu đó không có đáp án, vì người họp cả ngày hoặc hỗ
 * trợ đồng nghiệp sẽ không có task nào để gắn vào.
 *
 * Đây là mảnh còn thiếu của ba tính năng đã làm: chấm công cần nó để đối chiếu
 * "có giờ nhưng không có việc gì", thưởng cần nó để chấm điểm đóng góp, tổng
 * quan cần nó để nói về chất lượng chứ không chỉ số lượng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /*
            | Ngày báo cáo, theo GIỜ VIỆT NAM.
            |
            | Cùng quy ước và cùng lý do với `work_sessions.work_date`: giờ lưu
            | ở UTC, nên người viết báo cáo lúc 23:30 có `created_at` rơi vào
            | ngày UTC hôm trước. Dùng `App\Support\Time\WorkDate` để tính, một
            | chỗ duy nhất.
            */
            $table->date('report_date');

            $table->text('content');

            // draft → submitted → reviewed. Bản nháp là để người dùng viết dở
            // rồi quay lại, không phải trạng thái chờ duyệt.
            $table->string('status', 20)->default('draft');

            $table->timestamp('submitted_at')->nullable();

            // Người quản lý đã đọc. Câu hỏi lại (nếu có) nằm ở `review_note`.
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 1000)->nullable();

            $table->timestamps();

            // Một người một ngày một báo cáo.
            $table->unique(['user_id', 'report_date']);
            // Màn hình của quản lý quét theo ngày trước, lọc người sau.
            $table->index(['report_date', 'user_id']);
        });

        /*
        | Task đã đụng tới trong ngày.
        |
        | Khoá ngoại tới `tasks` ở tầng database, nhưng miền Report KHÔNG khai
        | quan hệ Eloquent tới model `Task`: deptrac chỉ cho
        | `Report → Identity, Support`. Tầng Http ghép tên task vào — cùng cách
        | đã dùng cho quỹ thưởng ghép với dự án.
        |
        | Danh sách này là tuỳ chọn: người họp cả ngày vẫn nộp được báo cáo mà
        | không gắn task nào. Bắt buộc phải có task là loại ràng buộc khiến
        | người ta bịa ra một task để nộp cho xong.
        */
        Schema::create('daily_report_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_report_id')
                ->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['daily_report_id', 'task_id']);
        });

        /*
        | Ảnh minh chứng CHƯA làm ở đợt này, có chủ ý.
        |
        | Công ty chọn chờ Cloudflare R2 (mục 1.10) rồi mới bật phần ảnh. Không
        | tạo bảng trống sẵn: bảng không ai ghi vào là bảng người đọc mã sau này
        | tưởng đã có tính năng. Khi bật, ảnh dùng chung hạ tầng medialibrary đã
        | có từ mục 1.5 — gắn vào `daily_reports` như một collection, không cần
        | migration mới.
        */
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_tasks');
        Schema::dropIfExists('daily_reports');
    }
};
