<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\Position;
use Illuminate\Database\Seeder;

/**
 * Cơ cấu tổ chức khởi đầu: chức vụ và cây phòng ban.
 *
 * Chạy được nhiều lần mà không tạo trùng — dùng updateOrCreate theo `code`.
 * Đây là dữ liệu nền, chạy cả trên production lúc go-live, không phải dữ liệu
 * giả để test.
 */
final class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPositions();
        $this->seedDepartments();
    }

    private function seedPositions(): void
    {
        $positions = [
            ['code' => 'NV', 'name' => 'Nhân viên', 'level' => 1],
            ['code' => 'TN', 'name' => 'Trưởng nhóm', 'level' => 2],
            ['code' => 'TP', 'name' => 'Trưởng phòng', 'level' => 3],
            ['code' => 'GD', 'name' => 'Giám đốc', 'level' => 4],
        ];

        foreach ($positions as $position) {
            Position::query()->updateOrCreate(
                ['code' => $position['code']],
                ['name' => $position['name'], 'level' => $position['level'], 'is_active' => true],
            );
        }
    }

    private function seedDepartments(): void
    {
        $congTy = Department::query()->updateOrCreate(
            ['code' => 'CTY'],
            ['name' => 'Công ty', 'parent_id' => null, 'is_active' => true],
        );

        // Cấu trúc mẫu. Công ty tự sửa lại cho khớp thực tế sau khi cài đặt.
        $phongBan = [
            ['code' => 'KD', 'name' => 'Phòng Kinh doanh'],
            ['code' => 'KT', 'name' => 'Phòng Kỹ thuật'],
            ['code' => 'MKT', 'name' => 'Phòng Marketing'],
            ['code' => 'HCNS', 'name' => 'Phòng Hành chính Nhân sự'],
            ['code' => 'TCKT', 'name' => 'Phòng Tài chính Kế toán'],
        ];

        foreach ($phongBan as $phong) {
            Department::query()->updateOrCreate(
                ['code' => $phong['code']],
                ['name' => $phong['name'], 'parent_id' => $congTy->id, 'is_active' => true],
            );
        }
    }
}
