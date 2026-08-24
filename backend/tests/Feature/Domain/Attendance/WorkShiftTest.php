<?php

declare(strict_types=1);

use App\Domain\Attendance\Data\WorkShift;

/*
|--------------------------------------------------------------------------
| Ca làm chuẩn của công ty
|--------------------------------------------------------------------------
|
| 8h15–12h sáng, 13h30–17h30 chiều. Đây là MỐC ĐẦU TIÊN hệ thống có để nói một
| người đến sớm hay muộn — trước đó chấm công chỉ đo tổng số phút trong ngày,
| không quan tâm đến lúc nào.
|
| Bẫy lớn nhất và là lý do file test này tồn tại: `first_seen_at` lấy từ MySQL
| là **giờ UTC**, còn 8h15 là **giờ Việt Nam**. Quên quy đổi thì lệch đúng 7
| tiếng — và lệch theo hướng tệ nhất: mọi người đều "đi muộn 7 tiếng".
|
*/

it('người đến trước giờ vào làm thì không muộn phút nào', function (): void {
    // 01:00 UTC = 08:00 giờ Việt Nam, sớm hơn 8h15.
    expect(WorkShift::fromConfig()->lateMinutes('2026-08-12 01:00:00'))->toBe(0);
});

it('đến đúng 8h15 vẫn chưa tính là muộn', function (): void {
    // Biên. Đúng giờ là đúng giờ, không phải muộn 0 phút một cách miễn cưỡng.
    expect(WorkShift::fromConfig()->lateMinutes('2026-08-12 01:15:00'))->toBe(0);
});

it('đến 8h30 là muộn 15 phút', function (): void {
    expect(WorkShift::fromConfig()->lateMinutes('2026-08-12 01:30:00'))->toBe(15);
});

it('quy đổi UTC sang giờ Việt Nam, không so thẳng', function (): void {
    /*
    | Test quan trọng nhất file. 08:20 UTC là 15:20 giờ Việt Nam — muộn 7 tiếng
    | 5 phút. Nếu ai đó lỡ so thẳng chuỗi UTC với "08:15" thì kết quả ra 5 phút,
    | và con số đó trông đủ hợp lý để không ai nghi ngờ.
    */
    expect(WorkShift::fromConfig()->lateMinutes('2026-08-12 08:20:00'))->toBe(425);
});

it('không có phiên làm việc nào thì không phải đi muộn', function (): void {
    // Vắng mặt cả ngày là chuyện khác hẳn với đi muộn. Gộp hai thứ vào một con
    // số là nói dối: người nghỉ phép sẽ thành "muộn 9 tiếng".
    expect(WorkShift::fromConfig()->lateMinutes(null))->toBe(0);
});

it('biết một ngày làm đủ là bao nhiêu phút', function (): void {
    // 8h15–12h = 225 phút, 13h30–17h30 = 240 phút.
    expect(WorkShift::fromConfig()->expectedMinutes())->toBe(465);
});

it('có thể nới thời gian ân hạn mà không sửa mã', function (): void {
    // Công ty muốn cho phép trễ 5 phút thì đổi config, không phải sửa logic.
    config()->set('attendance.shift.grace_minutes', 5);

    expect(WorkShift::fromConfig()->lateMinutes('2026-08-12 01:19:00'))->toBe(0)
        ->and(WorkShift::fromConfig()->lateMinutes('2026-08-12 01:21:00'))->toBe(6);
});

it('tính được số phút muộn từ một mốc giờ Việt Nam, không cần ngày', function (): void {
    /*
    | Dùng cho đơn xin đi muộn: người ta khai "9h30", chưa hề có phiên làm việc
    | nào để mà quy đổi từ UTC. Đây là phép trừ hai mốc trên đồng hồ, không phải
    | phép so hai thời điểm.
    */
    $ca = WorkShift::fromConfig();

    expect($ca->lateMinutesFromLocalTime('09:30'))->toBe(75)
        ->and($ca->lateMinutesFromLocalTime('08:15'))->toBe(0)
        // Sớm hơn giờ vào làm thì không âm — "muộn -20 phút" là vô nghĩa, và
        // một số âm lọt vào thông báo sẽ đọc rất kỳ.
        ->and($ca->lateMinutesFromLocalTime('07:55'))->toBe(0)
        // Nhận cả dạng có giây mà MySQL trả về.
        ->and($ca->lateMinutesFromLocalTime('10:00:00'))->toBe(105);
});
