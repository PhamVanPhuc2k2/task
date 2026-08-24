<?php

declare(strict_types=1);

use App\Support\Enums\ReportMatch;

/*
|--------------------------------------------------------------------------
| Luật đối chiếu giờ công với báo cáo ngày
|--------------------------------------------------------------------------
|
| Unit test thật — không database, không container. Làm được vì `ReportMatch`
| chỉ nhận số nguyên và boolean; mốc "có làm" truyền vào chứ không tự đọc
| config.
|
*/

const NGUONG = 60;

function doiChieu(int $phut, bool $coBaoCao, bool $laLe = false): ReportMatch
{
    return ReportMatch::for($phut, $coBaoCao, NGUONG, $laLe);
}

it('có giờ làm và có báo cáo là bình thường', function (): void {
    expect(doiChieu(480, true))->toBe(ReportMatch::Ok);
});

it('có giờ làm mà không báo cáo là thứ duy nhất cần người nhìn', function (): void {
    expect(doiChieu(480, false))->toBe(ReportMatch::MissingReport)
        ->and(doiChieu(480, false)->needsAttention())->toBeTrue();
});

it('có báo cáo mà gần như không có giờ thì KHÔNG bị coi là bất thường', function (): void {
    // Họp cả ngày, đi gặp khách, làm trên Word — đều rơi vào đây. Đếm nó là
    // bất thường thì người ta sẽ mở sẵn ứng dụng cho đủ giờ, đúng thói quen mà
    // cả tính năng chấm công sinh ra để bỏ.
    $kq = doiChieu(5, true);

    expect($kq)->toBe(ReportMatch::ReportOnly)
        ->and($kq->needsAttention())->toBeFalse();
});

it('không giờ không báo cáo là ngày trống, không phải lỗi', function (): void {
    expect(doiChieu(0, false))->toBe(ReportMatch::Idle)
        ->and(doiChieu(0, false)->needsAttention())->toBeFalse();
});

it('ngày lễ không đối chiếu gì cả', function (): void {
    // Kể cả khi có giờ làm và không có báo cáo — ngày lễ mà đi làm thì càng
    // không phải chuyện để gắn cờ nhắc nhở.
    expect(doiChieu(480, false, laLe: true))->toBe(ReportMatch::Holiday)
        ->and(doiChieu(480, false, laLe: true)->needsAttention())->toBeFalse();
});

it('mốc "có làm" là lớn-hơn-hoặc-bằng, không phải lớn hơn', function (): void {
    // Đúng 60 phút phải tính là có làm. Lệch một phút ở đây thì mỗi ngày có
    // vài người rơi nhầm nhóm, và không ai tìm ra vì sao.
    expect(doiChieu(NGUONG, false))->toBe(ReportMatch::MissingReport)
        ->and(doiChieu(NGUONG - 1, false))->toBe(ReportMatch::Idle);
});

it('mở ứng dụng xem thông báo rồi tắt không phải là một ngày làm việc', function (): void {
    expect(doiChieu(3, false))->toBe(ReportMatch::Idle);
});

it('mọi trạng thái đều có nhãn tiếng Việt', function (): void {
    foreach (ReportMatch::cases() as $case) {
        expect($case->label())->not->toBe('')
            ->and($case->label())->not->toContain('_');
    }
});
