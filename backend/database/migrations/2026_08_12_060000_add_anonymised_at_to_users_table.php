<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đánh dấu tài khoản đã bị xoá dữ liệu cá nhân theo Nghị định 13/2023/NĐ-CP.
 *
 * Cần một cột riêng chứ không suy ra từ tên hay email: giao diện phải phân biệt
 * được "người đã nghỉ việc" với "người đã nghỉ việc VÀ đã xoá dữ liệu cá nhân"
 * — hai trạng thái khác nhau, và chỉ trạng thái thứ hai là không đảo ngược
 * được. Đoán qua chuỗi tên là cách chắc chắn để một ngày nào đó đoán sai.
 *
 * Cột này cũng là bằng chứng đã thực hiện nghĩa vụ xoá, kèm mốc thời gian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('anonymised_at')->nullable()->after('terminated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('anonymised_at');
        });
    }
};
