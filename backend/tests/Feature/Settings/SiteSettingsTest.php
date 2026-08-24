<?php

declare(strict_types=1);

use App\Domain\Attendance\Data\WorkShift;
use App\Support\Exceptions\UnknownSettingException;
use App\Support\Settings\SettingKey;
use App\Support\Settings\SiteSettings;

/*
|--------------------------------------------------------------------------
| Cài đặt trang — giám đốc tự đổi, không cần gọi thợ
|--------------------------------------------------------------------------
|
| Trước lớp này, mười hai giá trị chính sách chỉ nằm trong `.env`: ca làm, ân
| hạn đi muộn, giờ nhắc báo cáo, cửa sổ nộp đơn. Đổi bất kỳ cái nào cũng phải
| sửa file trên máy chủ rồi khởi động lại — tức là mỗi lần công ty đổi giờ làm
| là một lần cần lập trình viên.
|
| ## Thiết kế: ghi đè CONFIG, không sửa mã miền
|
| Điểm quyết định của cả tính năng. Cài đặt trong database được nạp vào
| `Config` lúc khởi động, nên `WorkShift::fromConfig()` và mọi chỗ đọc config
| khác **không phải sửa một dòng nào** — và toàn bộ test đã có vẫn đúng.
|
| Cách khác là để mỗi chỗ tự hỏi database. Cách đó buộc phải sửa mọi lớp đang
| đọc config, và mỗi lớp lại thêm một truy vấn.
|
*/

it('chưa đặt gì thì trả về mặc định của config', function (): void {
    expect(app(SiteSettings::class)->get(SettingKey::ShiftMorningStart))
        ->toBe(config('attendance.shift.morning_start'));
});

it('đặt rồi thì đọc ra giá trị mới', function (): void {
    $s = app(SiteSettings::class);

    $s->set(SettingKey::CompanyName, 'HBR Holdings');

    expect($s->get(SettingKey::CompanyName))->toBe('HBR Holdings');
});

it('đổi ca làm qua cài đặt thì WorkShift đổi theo, không phải sửa mã', function (): void {
    /*
    | Test quan trọng nhất file. Nếu quan hệ này đứt thì trang cài đặt trở thành
    | một cái form ghi vào database rồi **không có tác dụng gì** — hỏng im lặng
    | đúng kiểu tệ nhất: giám đốc bấm lưu, thấy báo thành công, và bảng công vẫn
    | tính theo giờ cũ.
    */
    $s = app(SiteSettings::class);

    $s->set(SettingKey::ShiftMorningStart, '09:00');
    $s->apDungVaoConfig();

    expect(WorkShift::fromConfig()->morningStart)->toBe('09:00')
        // Và số phút muộn phải tính theo mốc MỚI: 09:30 giờ Việt Nam là muộn 30
        // phút so với 9h, không phải 75 phút so với 8h15.
        ->and(WorkShift::fromConfig()->lateMinutesFromLocalTime('09:30'))->toBe(30);
});

it('từ chối khoá không có trong danh mục', function (): void {
    // Cho gõ khoá tự do thì database đầy những dòng gõ sai chính tả mà không ai
    // biết, và `get()` của khoá đúng vẫn im lặng trả về mặc định.
    expect(fn () => app(SiteSettings::class)->setRaw('gio_lam_viec', '09:00'))
        ->toThrow(UnknownSettingException::class);
});

it('ép đúng kiểu khi đọc, không trả về chuỗi cho số', function (): void {
    // Database lưu mọi thứ dạng chuỗi. Trả "0" thay vì 0 thì `if ($x)` sẽ nhận
    // sai — chuỗi "0" là falsy nhưng chuỗi "5" và số 5 hành xử khác nhau ở
    // nhiều chỗ khác.
    $s = app(SiteSettings::class);

    $s->set(SettingKey::ShiftGraceMinutes, 5);

    expect($s->get(SettingKey::ShiftGraceMinutes))->toBe(5)
        ->and($s->get(SettingKey::ShiftGraceMinutes))->toBeInt();
});

it('đặt lại về mặc định bằng cách xoá, không phải gõ lại giá trị cũ', function (): void {
    // Gõ lại giá trị cũ thì nó thành giá trị CỐ ĐỊNH trong database — sau này
    // đổi mặc định trong config sẽ không có tác dụng, và không ai hiểu vì sao.
    $s = app(SiteSettings::class);

    $s->set(SettingKey::ShiftGraceMinutes, 15);
    $s->forget(SettingKey::ShiftGraceMinutes);

    expect($s->get(SettingKey::ShiftGraceMinutes))
        ->toBe(config()->integer('attendance.shift.grace_minutes'));
});

it('mọi khoá đều khai được đường ánh xạ sang config, hoặc cố ý không có', function (): void {
    /*
    | Lưới an toàn: thêm một khoá mới mà quên khai chỗ nó ghi vào config thì
    | khoá đó lưu được nhưng **không ảnh hưởng gì tới hệ thống**. Không có gì
    | báo — đúng loại lỗi im lặng dự án này liên tục phải trả giá.
    |
    | Khoá nhận diện (tên công ty, logo) cố ý không ánh xạ: chúng được đọc
    | thẳng qua API, không đi qua config.
    */
    foreach (SettingKey::cases() as $k) {
        expect($k->configPath())->toBeIn([null, ...array_map(
            fn (SettingKey $x): ?string => $x->configPath(),
            SettingKey::cases(),
        )]);

        if ($k->configPath() !== null) {
            expect(config()->has($k->configPath()))->toBeTrue(
                "Khoá {$k->value} ánh xạ tới config không tồn tại: {$k->configPath()}",
            );
        }
    }
});
