<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Địa chỉ IP thật khi có proxy đứng trước
|--------------------------------------------------------------------------
|
| Trên production, một request đi qua ba chặng trước khi tới PHP:
|
|   Trình duyệt → Cloudflare → nginx của máy chủ → nginx trong Docker → php-fpm
|
| Không khai báo proxy tin cậy thì `$request->ip()` trả về địa chỉ của **chặng
| cuối cùng** — tức là mọi người dùng trên đời đều mang chung một IP.
|
| Đây không phải chuyện thẩm mỹ. `$request->ip()` đang được dùng ở năm chỗ, và
| bốn trong số đó là giới hạn tần suất:
|
|   - Quên mật khẩu: 10 lần / IP / 15 phút → thành 10 lần cho CẢ CÔNG TY
|   - Nhập mã 2FA, gửi lại mã 2FA, đăng nhập
|   - Nhật ký đăng nhập: ghi lại IP của proxy, vô dụng khi cần truy vết
|
| Tức là hệ thống tự chặn chính mình, và nhật ký bảo mật mất giá trị — cả hai
| đều im lặng, không có gì báo lỗi.
|
*/

it('lấy đúng IP người dùng khi request đi qua proxy nội bộ', function (): void {
    // 172.18.x.x là dải mạng nội bộ của Docker — chặng cuối trước php-fpm.
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.45, 172.18.0.5'])
        ->getJson('/api/v1/health')
        ->assertOk();

    expect(request()->ip())->toBe('203.0.113.45');
});

it('biết request gốc là HTTPS dù chặng cuối là HTTP', function (): void {
    /*
    | nginx trong Docker nói chuyện với php-fpm bằng HTTP, luôn luôn. Không đọc
    | `X-Forwarded-Proto` thì Laravel tưởng cả kết nối là HTTP, và mọi đường dẫn
    | nó tự sinh ra — liên kết đặt lại mật khẩu chẳng hạn — mang tiền tố
    | `http://`. Trình duyệt chặn, người dùng không đặt lại được mật khẩu.
    */
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->getJson('/api/v1/health');

    expect(request()->isSecure())->toBeTrue();
});

it('KHÔNG tin header giả từ địa chỉ công cộng', function (): void {
    /*
    | Lưới an toàn quan trọng nhất. Tin mọi proxy (`at: '*'`) thì bất kỳ ai cũng
    | tự khai IP của mình bằng cách gửi kèm `X-Forwarded-For` — và mọi giới hạn
    | tần suất theo IP trở thành vô dụng, vì kẻ dò mật khẩu chỉ cần đổi con số
    | trong header sau mỗi lần thử.
    |
    | Chỉ dải mạng nội bộ mới được tin, vì php-fpm không mở ra internet: muốn
    | gửi được request tới nó thì đã phải ở trong mạng Docker rồi.
    */
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.45'])
        ->getJson('/api/v1/health');

    expect(request()->ip())->toBe('198.51.100.9');
});
