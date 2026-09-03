<?php

declare(strict_types=1);

use App\Domain\Attendance\Data\WorkWeek;

/*
|--------------------------------------------------------------------------
| Lịch làm việc trong tuần
|--------------------------------------------------------------------------
|
| Công ty chốt tháng 9/2026: thứ hai tới thứ sáu làm cả ngày, thứ bảy làm buổi
| sáng, chủ nhật nghỉ.
|
| Điều đáng kiểm nhất ở đây không phải "thứ bảy có ca không" mà là **ngày nghỉ
| KHÔNG tính đi muộn**. Trước khi có lớp này, ca chuẩn 08:15 áp cho mọi ngày mà
| không hề kiểm thứ mấy — người làm chiều thứ bảy bị tính muộn hơn năm tiếng, và
| dấu đi muộn thì không tự biến mất.
|
| Mốc ngày dùng chung: 12/08/2026 là thứ tư, 15/08 thứ bảy, 16/08 chủ nhật.
|
*/

it('thứ hai tới thứ sáu là ca cả ngày', function (): void {
    $ca = WorkWeek::fromConfig()->shiftFor('2026-08-12');

    expect($ca)->not->toBeNull()
        ->and($ca?->morningStart)->toBe('08:15')
        ->and($ca?->end)->toBe('17:30')
        ->and($ca?->expectedMinutes())->toBe(465);
});

it('thứ bảy là ca nửa buổi, tan lúc 12h và không có nghỉ trưa', function (): void {
    $ca = WorkWeek::fromConfig()->shiftFor('2026-08-15');

    expect($ca)->not->toBeNull()
        ->and($ca?->morningStart)->toBe('08:15')
        ->and($ca?->end)->toBe('12:00')
        // 08:15 → 12:00 là 225 phút. Buổi chiều bằng không vì ba mốc còn lại
        // đều là giờ tan — xem WorkShift::halfDay().
        ->and($ca?->expectedMinutes())->toBe(225);
});

it('chủ nhật không có ca nào', function (): void {
    expect(WorkWeek::fromConfig()->shiftFor('2026-08-16'))->toBeNull();
});

it('ngày nghỉ KHÔNG tính đi muộn', function (): void {
    /*
    | Lỗi mà cả lớp này sinh ra để chặn.
    |
    | Người làm chủ nhật vẫn được tính đủ số phút — công ty làm remote, làm cuối
    | tuần là chuyện bình thường. Nhưng so giờ vào của họ với một ca không tồn
    | tại thì ra "muộn mấy tiếng", và bảng công đầy dấu đỏ cho người làm thêm.
    */
    $tuan = WorkWeek::fromConfig();

    // 07:00 UTC = 14:00 giờ Việt Nam, chủ nhật 16/08.
    expect($tuan->shiftFor('2026-08-16'))->toBeNull();

    // Cùng mốc giờ đó vào thứ tư thì đúng là muộn — để chắc con số 0 ở trên
    // đến từ "không có ca", không phải từ một phép tính hỏng.
    expect($tuan->shiftFor('2026-08-12')?->lateMinutes('2026-08-12 07:00:00'))
        ->toBe(345);
});

it('thứ bảy vẫn tính đi muộn, theo cùng giờ vào làm', function (): void {
    // Giờ vào làm dùng chung `morning_start` cho mọi ngày có ca — không ai muốn
    // nhớ hai mốc khác nhau. 02:00 UTC = 09:00 giờ Việt Nam, muộn 45 phút.
    expect(WorkWeek::fromConfig()->shiftFor('2026-08-15')?->lateMinutes('2026-08-15 02:00:00'))
        ->toBe(45);
});

it('trần giờ ngày nửa buổi thấp hơn ngày thường', function (): void {
    /*
    | Trần 600 phút áp lên một buổi sáng 225 phút nghĩa là cái tab quên đóng
    | chiều thứ bảy vẫn ghi thẳng 10 tiếng công. Vài lần như vậy là không ai còn
    | tin bảng công.
    |
    | Ngày nghỉ dùng chung trần thấp đó: bỏ trần hẳn thì tab quên đóng tối thứ
    | bảy chạy suốt chủ nhật ghi hai mươi bốn tiếng.
    */
    $tuan = WorkWeek::fromConfig();

    expect($tuan->maxDailyMinutesFor('2026-08-12'))->toBe(600)
        ->and($tuan->maxDailyMinutesFor('2026-08-15'))->toBe(360)
        ->and($tuan->maxDailyMinutesFor('2026-08-16'))->toBe(360);
});

it('ngày nửa buổi không phải ngày nghỉ', function (): void {
    // Quyết định này chảy thẳng vào nghỉ bù theo khoản 3 Điều 112: lễ rơi thứ
    // bảy làm việc thì không sinh nghỉ bù.
    expect(WorkWeek::fromConfig()->restDays())->toBe([0]);
});

it('bỏ làm thứ bảy thì cuối tuần thành ngày nghỉ trở lại', function (): void {
    config()->set('attendance.work_days_half', '');

    $tuan = WorkWeek::fromConfig();

    expect($tuan->restDays())->toBe([0, 6])
        ->and($tuan->shiftFor('2026-08-15'))->toBeNull()
        ->and($tuan->isWorkingDay('2026-08-15'))->toBeFalse();
});

it('làm cả ngày thứ bảy cũng khai được', function (): void {
    config()->set('attendance.work_days_full', '1,2,3,4,5,6');
    config()->set('attendance.work_days_half', '');

    $tuan = WorkWeek::fromConfig();

    expect($tuan->shiftFor('2026-08-15')?->expectedMinutes())->toBe(465)
        ->and($tuan->maxDailyMinutesFor('2026-08-15'))->toBe(600)
        ->and($tuan->restDays())->toBe([0]);
});

it('bỏ qua giá trị rác thay vì ném lỗi', function (): void {
    /*
    | Cấu hình này giám đốc sửa được trên giao diện. Một ký tự thừa lọt qua
    | validate không nên làm sập cả trang chấm công của công ty — nó chỉ nên làm
    | ngày đó thành ngày nghỉ. Lớp chặn thật nằm ở UpdateSettingsRequest.
    */
    config()->set('attendance.work_days_full', '1, 2 ,x,9,,2,3');
    config()->set('attendance.work_days_half', '');

    $tuan = WorkWeek::fromConfig();

    // Rác bị bỏ, khoảng trắng được cắt, giá trị trùng chỉ tính một lần.
    expect($tuan->full)->toBe([1, 2, 3]);
});

it('không có ngày làm việc nào thì mọi ngày đều là ngày nghỉ', function (): void {
    // Không ném lỗi ở tầng này — nó chỉ trung thực báo lại điều cấu hình nói.
    // Chặn cấu hình vô nghĩa là việc của UpdateSettingsRequest.
    config()->set('attendance.work_days_full', '');
    config()->set('attendance.work_days_half', '');

    $tuan = WorkWeek::fromConfig();

    expect($tuan->restDays())->toBe([0, 1, 2, 3, 4, 5, 6])
        ->and($tuan->isWorkingDay('2026-08-12'))->toBeFalse();
});
