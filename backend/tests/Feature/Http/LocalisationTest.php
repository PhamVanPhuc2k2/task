<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;

/*
|--------------------------------------------------------------------------
| Thông báo lỗi phải bằng tiếng Việt
|--------------------------------------------------------------------------
|
| `APP_LOCALE=vi` đã đặt từ đầu nhưng thư mục `lang/` chưa từng tồn tại, nên
| Laravel âm thầm rơi về bản tiếng Anh trong vendor suốt nhiều đợt. Không có gì
| báo — chỉ người dùng thấy "The email field is required." giữa một giao diện
| tiếng Việt.
|
| Loại lỗi này sẽ quay lại y hệt nếu ai đó xoá `lang/vi`, đổi `APP_LOCALE`, hay
| chạy `config:cache` trên một môi trường thiếu biến. Mấy test dưới đây là thứ
| duy nhất phát hiện được.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

it('trả thông báo thiếu trường bằng tiếng Việt', function (): void {
    $loi = $this->actingAs(quanTri())
        ->postJson('/api/v1/users', [])
        ->assertStatus(422)
        ->json('errors');

    expect($loi['name'][0])->toBe('Chưa nhập họ tên.')
        ->and($loi['email'][0])->toBe('Chưa nhập email.')
        // Không còn dấu vết bản tiếng Anh dựng sẵn.
        ->and(json_encode($loi))->not->toContain('field is required');
});

it('viết hoa chữ đầu khi tên trường đứng đầu câu', function (): void {
    // Laravel thay `:Attribute` bằng bản ucfirst của tên trường. Dùng
    // `:attribute` ở đầu câu thì ra "email này đã có người dùng." — viết
    // thường giữa một câu hoàn chỉnh.
    $nguoiCu = User::factory()->create(['email' => 'da.ton.tai@congty.vn']);

    $loi = $this->actingAs(quanTri())
        ->postJson('/api/v1/users', [
            'name' => 'Nguyễn Văn A',
            'email' => $nguoiCu->email,
            'employee_code' => 'NV9999',
            'role' => Role::NhanVien->value,
        ])
        ->assertStatus(422)
        ->json('errors.email.0');

    expect($loi)->toBe('Email này đã có người dùng.');
});

it('dịch cả tên trường mà FormRequest quên khai', function (): void {
    // `StoreUserRequest::attributes()` khai bảy trường nhưng bỏ sót `phone`.
    // Không có lưới an toàn ở `lang/vi/validation.php` thì người dùng nhận câu
    // "phone không được dài quá 20 ký tự." — đúng thứ đã xảy ra khi chạy thật.
    $loi = $this->actingAs(quanTri())
        ->postJson('/api/v1/users', [
            'name' => 'Nguyễn Văn A',
            'email' => 'a.nguyen@congty.vn',
            'employee_code' => 'NV9999',
            'role' => Role::NhanVien->value,
            'phone' => str_repeat('0', 30),
        ])
        ->assertStatus(422)
        ->json('errors.phone.0');

    expect($loi)->toBe('Số điện thoại không được dài quá 20 ký tự.');
});

it('không còn tên trường thô nào lọt ra trong thông báo lỗi', function (): void {
    // Quét một loạt form và khẳng định không có tên cột dạng snake_case nào
    // xuất hiện trong câu thông báo — đó là dấu hiệu FormRequest quên khai và
    // lưới an toàn cũng chưa phủ tới.
    $admin = quanTri();

    $duong = [
        '/api/v1/users',
        '/api/v1/tasks',
        '/api/v1/projects',
    ];

    foreach ($duong as $d) {
        $noiDung = (string) $this->actingAs($admin)
            ->postJson($d, [])
            ->assertStatus(422)
            ->getContent();

        $cau = implode(' ', array_merge(...array_values(
            (array) json_decode($noiDung, associative: true)['errors'],
        )));

        expect($cau)->not->toMatch('/\b[a-z]+_[a-z_]+\b/');
    }
});
