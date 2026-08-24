<?php

declare(strict_types=1);

use App\Domain\Identity\Models\Department;

it('dựng được cây phòng ban nhiều cấp', function (): void {
    $congTy = Department::factory()->create(['name' => 'Công ty']);
    $khoiKinhDoanh = Department::factory()->for($congTy, 'parent')->create(['name' => 'Khối Kinh doanh']);
    $phongSale = Department::factory()->for($khoiKinhDoanh, 'parent')->create(['name' => 'Phòng Sale']);

    expect($phongSale->parent?->name)->toBe('Khối Kinh doanh')
        ->and($khoiKinhDoanh->parent?->name)->toBe('Công ty')
        ->and($congTy->parent)->toBeNull()
        ->and($congTy->children->pluck('name')->all())->toBe(['Khối Kinh doanh']);
});

it('trả về toàn bộ phòng ban cấp dưới ở mọi cấp sâu', function (): void {
    // Đây là thứ quyết định "sếp xem được task của phòng mình và các phòng
    // trực thuộc". Chỉ lấy con trực tiếp là thiếu.
    $congTy = Department::factory()->create(['name' => 'Công ty']);
    $khoi = Department::factory()->for($congTy, 'parent')->create(['name' => 'Khối Kinh doanh']);
    $sale = Department::factory()->for($khoi, 'parent')->create(['name' => 'Phòng Sale']);
    $telesale = Department::factory()->for($sale, 'parent')->create(['name' => 'Tổ Telesale']);
    $ketToan = Department::factory()->for($congTy, 'parent')->create(['name' => 'Phòng Kế toán']);

    $ids = $khoi->descendantIds();

    expect($ids)->toContain($sale->id, $telesale->id)
        ->and($ids)->not->toContain($ketToan->id, $congTy->id, $khoi->id);
});

it('gồm cả chính nó khi lấy phạm vi quản lý', function (): void {
    // Đặt tên subtreeIds chứ không phải scopeIds: Eloquent coi mọi phương thức
    // bắt đầu bằng "scope" là query scope, gọi trực tiếp trên instance sẽ lỗi
    // vì thiếu tham số $query.
    $khoi = Department::factory()->create();
    $sale = Department::factory()->for($khoi, 'parent')->create();

    expect($khoi->subtreeIds())->toContain($khoi->id, $sale->id);
});

it('lộ ra uuid thay vì id tuần tự', function (): void {
    $department = Department::factory()->create();

    expect($department->uuid)->toBeString()->toHaveLength(36);
});
