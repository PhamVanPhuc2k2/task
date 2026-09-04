<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Đơn vượt hạn mức nghỉ không lương hoặc hạn mức xin đi muộn.
 *
 * ## Chặn ở bước nộp, không phải ở bước duyệt
 *
 * Cho nộp rồi bắt người duyệt từ chối là lãng phí thời gian cả hai bên: nhân
 * viên chờ vài ngày để nhận một câu trả lời mà hệ thống biết ngay từ đầu, còn
 * quản lý phải tự cộng nhẩm xem người này đã dùng bao nhiêu.
 *
 * Đây là chỗ hạn mức khác với chấm công. Giờ công thì hệ thống **đo và gắn cờ**,
 * con người quyết định — vì phép đo có thể sai. Hạn mức thì không có gì để sai:
 * nó là con số giám đốc đặt ra, và số ngày đã dùng là dữ liệu chắc chắn.
 *
 * ## Câu chữ phải nói ra con số
 *
 * "Bạn đã vượt hạn mức" là câu vô dụng — người đọc không biết mình đã dùng bao
 * nhiêu, còn bao nhiêu, hay phải sửa đơn thế nào. Mọi thông báo ở đây đều nói
 * đủ ba con số: đã dùng, hạn mức, và đơn này cần thêm bao nhiêu.
 */
final class LeaveQuotaExceededException extends DomainException
{
    private function __construct(string $message, private readonly string $truong)
    {
        parent::__construct($message);
    }

    public static function nghiKhongLuong(int $nam, int $daDung, int $hanMuc, int $canThem): self
    {
        return new self(
            sprintf(
                'Đơn này vượt hạn mức nghỉ không lương của năm %d. Bạn đã dùng %d/%d ngày, đơn này cần thêm %d ngày. Trao đổi với quản lý nếu cần ngoại lệ.',
                $nam,
                $daDung,
                $hanMuc,
                $canThem,
            ),
            'start_date',
        );
    }

    public static function xinDiMuon(string $thang, int $daDung, int $hanMuc): self
    {
        return new self(
            sprintf(
                'Bạn đã dùng hết %d/%d lần xin đi muộn của tháng %s. Trao đổi với quản lý nếu cần ngoại lệ.',
                $daDung,
                $hanMuc,
                $thang,
            ),
            'date',
        );
    }

    public function errorCode(): string
    {
        return 'LEAVE_QUOTA_EXCEEDED';
    }

    /**
     * Gắn lỗi vào đúng ô người dùng phải sửa.
     *
     * Đơn nghỉ thì sửa ngày bắt đầu/kết thúc; đơn đi muộn thì đổi ngày sang
     * tháng khác. Rơi xuống dải lỗi chung thì người dùng phải tự đoán.
     *
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return [$this->truong => [$this->getMessage()]];
    }
}
