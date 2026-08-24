<?php

declare(strict_types=1);

use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\Team;
use App\Domain\Identity\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

it('gom được thành viên từ nhiều phòng ban khác nhau vào một đội', function (): void {
    // Đội nhóm cắt ngang cơ cấu phòng ban: một dự án có thể cần người từ
    // Sale, Kỹ thuật và Kế toán cùng lúc.
    $sale = Department::factory()->create();
    $kyThuat = Department::factory()->create();

    $nguoiSale = User::factory()->for($sale, 'department')->create();
    $nguoiKyThuat = User::factory()->for($kyThuat, 'department')->create();

    $doi = Team::factory()->create(['name' => 'Đội triển khai']);
    $doi->members()->attach([$nguoiSale->id, $nguoiKyThuat->id]);

    expect($doi->members)->toHaveCount(2)
        ->and($doi->members->pluck('id')->all())
        ->toEqualCanonicalizing([$nguoiSale->id, $nguoiKyThuat->id]);
});

it('cho một nhân viên thuộc nhiều đội cùng lúc', function (): void {
    $nhanVien = User::factory()->create();
    $doiA = Team::factory()->create();
    $doiB = Team::factory()->create();

    $nhanVien->teams()->attach([$doiA->id, $doiB->id]);

    expect($nhanVien->teams->pluck('id')->all())
        ->toEqualCanonicalizing([$doiA->id, $doiB->id]);
});

it('không cho thêm trùng một người vào cùng một đội', function (): void {
    $nhanVien = User::factory()->create();
    $doi = Team::factory()->create();

    $doi->members()->attach($nhanVien->id);

    expect(fn () => $doi->members()->attach($nhanVien->id))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('ghi nhận người phụ trách đội', function (): void {
    $truongDoi = User::factory()->create(['name' => 'Trưởng đội']);
    $doi = Team::factory()->for($truongDoi, 'leader')->create();

    expect($doi->leader?->name)->toBe('Trưởng đội');
});
