<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\Position;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\ProjectRole;
use App\Domain\Task\Enums\ProjectStatus;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Project;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskComment;
use App\Domain\Task\Models\TaskLabel;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu giả để dev và demo. KHÔNG chạy trên production.
 *
 *     php artisan db:seed --class=DemoDataSeeder
 */
final class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('DemoDataSeeder không được chạy trên production.');

            return;
        }

        $phongKinhDoanh = Department::query()->where('code', 'KD')->firstOrFail();
        $chucVuTP = Position::query()->where('code', 'TP')->firstOrFail();
        $chucVuNV = Position::query()->where('code', 'NV')->firstOrFail();

        $truongPhong = User::factory()->create([
            'name' => 'Lê Minh Tuấn',
            'email' => 'tuan.le@demo.vn',
            'department_id' => $phongKinhDoanh->id,
            'position_id' => $chucVuTP->id,
        ]);

        // Gán vai trò là BẮT BUỘC, không phải trang trí. Toàn bộ quyền đi qua
        // vai trò — tài khoản không có vai trò nào thì đăng nhập vào không
        // thấy nổi task của chính mình, vì thiếu cả `task.view.own`. Dữ liệu
        // demo mà không demo được gì thì không phải dữ liệu demo.
        $truongPhong->syncRoles([Role::TruongPhong->value]);

        $nhanVien = User::factory()->count(4)->create([
            'department_id' => $phongKinhDoanh->id,
            'position_id' => $chucVuNV->id,
            'manager_id' => $truongPhong->id,
        ]);

        $nhanVien->each(fn (User $u) => $u->syncRoles([Role::NhanVien->value]));

        $nhanLabels = collect(['Gấp', 'Khách VIP', 'Nội bộ'])
            ->map(fn (string $ten): TaskLabel => TaskLabel::query()->firstOrCreate(['name' => $ten]));

        $duAn = Project::factory()->create([
            'name' => 'Triển khai hệ thống cho khách A',
            'owner_id' => $truongPhong->id,
            'department_id' => $phongKinhDoanh->id,
            'status' => ProjectStatus::Active,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(2),
        ]);

        $duAn->members()->attach($truongPhong->id, ['role' => ProjectRole::Manager->value]);

        foreach ($nhanVien as $index => $nguoi) {
            $duAn->members()->attach($nguoi->id, ['role' => ProjectRole::Member->value]);

            $task = Task::factory()->for($duAn)->create([
                'title' => 'Công việc demo số '.($index + 1),
                'assignee_id' => $nguoi->id,
                'assigner_id' => $truongPhong->id,
                'status' => [TaskStatus::Todo, TaskStatus::InProgress, TaskStatus::Review, TaskStatus::Done][$index],
                'priority' => [TaskPriority::Low, TaskPriority::Normal, TaskPriority::High, TaskPriority::Urgent][$index],
                'due_date' => now()->addDays($index * 3 - 2),
                'estimate_hours' => '8.00',
                'created_by' => $truongPhong->id,
            ]);

            $task->labels()->attach($nhanLabels->random()->id);
            $task->watchers()->attach($truongPhong->id);

            TaskComment::factory()->for($task)->for($truongPhong, 'author')->create([
                'body' => 'Em cập nhật tiến độ giúp anh nhé.',
            ]);
        }

        $this->command->info(sprintf(
            'Đã tạo dữ liệu demo: 1 dự án, %d task, %d nhân viên.',
            $nhanVien->count(),
            $nhanVien->count() + 1,
        ));
    }
}
