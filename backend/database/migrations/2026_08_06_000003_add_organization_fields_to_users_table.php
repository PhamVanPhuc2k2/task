<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung các trường tổ chức vào bảng users do Laravel sinh sẵn.
 *
 * Viết migration mới thay vì sửa migration gốc: giữ nguyên file của Laravel để
 * lần nâng cấp framework sau không phải gỡ xung đột, và tuân thủ quy ước
 * "migration chỉ tiến" trong README.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid()->nullable()->after('id')->unique();

            $table->foreignId('department_id')->nullable()->after('email')
                ->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->after('department_id')
                ->constrained('positions')->nullOnDelete();

            // Quản lý trực tiếp. nullOnDelete để sếp nghỉ việc không kéo theo
            // việc xoá cấp dưới.
            $table->foreignId('manager_id')->nullable()->after('position_id')
                ->constrained('users')->nullOnDelete();

            $table->string('employee_code')->nullable()->after('manager_id')->unique();
            $table->string('phone', 20)->nullable()->after('employee_code');
            $table->date('joined_at')->nullable()->after('phone');

            // Nhân viên nghỉ việc KHÔNG bị xoá: task họ từng làm, báo cáo họ
            // từng nộp và bảng công của họ đều phải còn nguyên vết.
            $table->boolean('is_active')->default(true)->after('joined_at');
            $table->timestamp('terminated_at')->nullable()->after('is_active');

            $table->softDeletes();

            $table->index(['department_id', 'is_active']);
            $table->index('manager_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['position_id']);
            $table->dropForeign(['manager_id']);

            $table->dropIndex(['department_id', 'is_active']);
            $table->dropIndex(['manager_id']);

            $table->dropColumn([
                'uuid',
                'department_id',
                'position_id',
                'manager_id',
                'employee_code',
                'phone',
                'joined_at',
                'is_active',
                'terminated_at',
                'deleted_at',
            ]);
        });
    }
};
