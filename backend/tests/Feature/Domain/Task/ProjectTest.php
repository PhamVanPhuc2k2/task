<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\ProjectRole;
use App\Domain\Task\Enums\ProjectStatus;
use App\Domain\Task\Models\Project;
use Illuminate\Database\UniqueConstraintViolationException;

it('ghi nhận người phụ trách dự án', function (): void {
    $truongDuAn = User::factory()->create(['name' => 'Trưởng dự án']);
    $duAn = Project::factory()->for($truongDuAn, 'owner')->create();

    expect($duAn->owner?->name)->toBe('Trưởng dự án');
});

it('mặc định dự án mới ở trạng thái đang lên kế hoạch', function (): void {
    expect(Project::factory()->create()->status)->toBe(ProjectStatus::Planning);
});

it('cast trạng thái sang enum chứ không trả về chuỗi thô', function (): void {
    $duAn = Project::factory()->create(['status' => ProjectStatus::Active]);
    $duAn->refresh();

    expect($duAn->status)->toBeInstanceOf(ProjectStatus::class)
        ->and($duAn->status)->toBe(ProjectStatus::Active);
});

it('có nhãn tiếng Việt cho mọi trạng thái dự án', function (): void {
    expect(ProjectStatus::Active->label())->toBe('Đang chạy');

    foreach (ProjectStatus::cases() as $status) {
        expect($status->label())->not->toBeEmpty();
    }
});

it('thêm thành viên vào dự án kèm vai trò trong dự án', function (): void {
    $duAn = Project::factory()->create();
    $quanLy = User::factory()->create();
    $thanhVien = User::factory()->create();

    $duAn->members()->attach($quanLy->id, ['role' => ProjectRole::Manager->value]);
    $duAn->members()->attach($thanhVien->id, ['role' => ProjectRole::Member->value]);

    $vaiTro = $duAn->members->pluck('pivot.role', 'email');

    expect($vaiTro[$quanLy->email])->toBe('manager')
        ->and($vaiTro[$thanhVien->email])->toBe('member');
});

it('không cho thêm trùng một người vào cùng một dự án', function (): void {
    $duAn = Project::factory()->create();
    $nguoi = User::factory()->create();

    $duAn->members()->attach($nguoi->id, ['role' => ProjectRole::Member->value]);

    expect(fn () => $duAn->members()->attach($nguoi->id, ['role' => ProjectRole::Member->value]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('phân biệt dự án còn mở và dự án đã đóng', function (): void {
    expect(ProjectStatus::Active->isOpen())->toBeTrue()
        ->and(ProjectStatus::Completed->isOpen())->toBeFalse()
        ->and(ProjectStatus::Cancelled->isOpen())->toBeFalse();
});
