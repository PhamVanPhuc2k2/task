<?php

declare(strict_types=1);

namespace App\Support\Settings;

/**
 * Danh mục cài đặt trang. **Nguồn sự thật duy nhất.**
 *
 * ## Vì sao là enum chứ không phải chuỗi tự do
 *
 * Bảng key/value cho gõ khoá tuỳ ý thì database đầy những dòng sai chính tả mà
 * không ai biết — và `get()` của khoá đúng vẫn im lặng trả về mặc định. Enum
 * biến lỗi đó thành lỗi lúc biên dịch.
 *
 * ## Vì sao key/value chứ không phải mỗi cài đặt một cột
 *
 * Thêm một cài đặt mới thì chỉ thêm một `case` ở đây, **không cần migration**.
 * Với dự án này đó là lý do rất thật: bộ quét migration của Larastan đang nằm
 * sát ngưỡng, mỗi migration mới là một lần đánh cược (xem mục "Larastan sập
 * khi thêm migration").
 *
 * ## Ánh xạ sang config là chỗ quyết định
 *
 * `configPath()` nói giá trị này ghi vào đâu trong `Config` lúc khởi động. Nhờ
 * nó mà `WorkShift::fromConfig()` và mọi chỗ đọc config khác **không phải sửa
 * một dòng nào**. Khoá trả về `null` là khoá cố ý không đi qua config — nhận
 * diện (tên, logo, biểu tượng) được đọc thẳng qua API.
 */
enum SettingKey: string
{
    // ── Nhận diện ────────────────────────────────────────────────────────
    case CompanyName = 'company_name';
    case CompanyShortName = 'company_short_name';
    case LogoPath = 'logo_path';
    case IconPath = 'icon_path';

    // ── Ca làm ───────────────────────────────────────────────────────────
    case ShiftMorningStart = 'shift_morning_start';
    case ShiftLunchStart = 'shift_lunch_start';
    case ShiftLunchEnd = 'shift_lunch_end';
    case ShiftEnd = 'shift_end';
    case ShiftGraceMinutes = 'shift_grace_minutes';
    case MinWorkedMinutes = 'min_worked_minutes';
    case MaxDailyMinutes = 'max_daily_minutes';

    // ── Báo cáo ngày ─────────────────────────────────────────────────────
    case ReportReminderEnabled = 'report_reminder_enabled';
    case ReportReminderAt = 'report_reminder_at';
    case ReportBackfillDays = 'report_backfill_days';

    // ── Nghỉ phép ────────────────────────────────────────────────────────
    case LeaveBackdateDays = 'leave_backdate_days';
    case LeaveFutureDays = 'leave_future_days';
    case LeaveMaxDays = 'leave_max_days';

    /** Đường dẫn trong `Config`, hoặc `null` nếu khoá này không đi qua config. */
    public function configPath(): ?string
    {
        return match ($this) {
            self::CompanyName, self::CompanyShortName,
            self::LogoPath, self::IconPath => null,

            self::ShiftMorningStart => 'attendance.shift.morning_start',
            self::ShiftLunchStart => 'attendance.shift.lunch_start',
            self::ShiftLunchEnd => 'attendance.shift.lunch_end',
            self::ShiftEnd => 'attendance.shift.end',
            self::ShiftGraceMinutes => 'attendance.shift.grace_minutes',
            self::MinWorkedMinutes => 'attendance.min_worked_minutes',
            self::MaxDailyMinutes => 'attendance.max_daily_minutes',

            self::ReportReminderEnabled => 'reports.reminder.enabled',
            self::ReportReminderAt => 'reports.reminder.at',
            self::ReportBackfillDays => 'reports.backfill_days',

            self::LeaveBackdateDays => 'leave.backdate_days',
            self::LeaveFutureDays => 'leave.future_days',
            self::LeaveMaxDays => 'leave.max_days_per_request',
        };
    }

    public function type(): SettingType
    {
        return match ($this) {
            self::ShiftGraceMinutes,
            self::MinWorkedMinutes,
            self::MaxDailyMinutes,
            self::ReportBackfillDays,
            self::LeaveBackdateDays,
            self::LeaveFutureDays,
            self::LeaveMaxDays => SettingType::Integer,

            self::ReportReminderEnabled => SettingType::Boolean,

            default => SettingType::Text,
        };
    }

    /**
     * Giá trị mặc định khi chưa ai đặt.
     *
     * Lấy từ `config`, KHÔNG viết lại số ở đây: hai nơi khai cùng một mặc định
     * là hai nơi sẽ lệch nhau sau lần sửa đầu tiên. Khoá nhận diện không có
     * đường config nên có mặc định riêng.
     */
    public function default(): string|int|bool|null
    {
        $duong = $this->configPath();

        if ($duong !== null) {
            /** @var string|int|bool|null $v */
            $v = config($duong);

            return $v;
        }

        return match ($this) {
            self::CompanyName => 'Explus',
            self::CompanyShortName => 'explus',
            self::LogoPath, self::IconPath => null,
            default => null,
        };
    }

    /** Nhãn hiện trên giao diện. */
    public function label(): string
    {
        return match ($this) {
            self::CompanyName => 'Tên công ty',
            self::CompanyShortName => 'Tên ngắn (hiện cạnh logo)',
            self::LogoPath => 'Logo',
            self::IconPath => 'Biểu tượng',

            self::ShiftMorningStart => 'Giờ vào làm buổi sáng',
            self::ShiftLunchStart => 'Bắt đầu nghỉ trưa',
            self::ShiftLunchEnd => 'Hết nghỉ trưa',
            self::ShiftEnd => 'Giờ tan làm',
            self::ShiftGraceMinutes => 'Số phút châm chước trước khi tính muộn',
            self::MinWorkedMinutes => 'Số phút tối thiểu để coi là có đi làm',
            self::MaxDailyMinutes => 'Trần giờ công tự động mỗi ngày (phút)',

            self::ReportReminderEnabled => 'Nhắc nộp báo cáo cuối ngày',
            self::ReportReminderAt => 'Giờ gửi nhắc',
            self::ReportBackfillDays => 'Số ngày được nộp báo cáo bù',

            self::LeaveBackdateDays => 'Số ngày được xin nghỉ lùi về quá khứ',
            self::LeaveFutureDays => 'Số ngày được xin nghỉ trước',
            self::LeaveMaxDays => 'Số ngày tối đa một đơn nghỉ',
        };
    }
}
