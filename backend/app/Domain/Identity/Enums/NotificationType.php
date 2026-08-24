<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/**
 * Các loại thông báo người dùng có thể bật/tắt.
 *
 * Đặt ở miền Identity chứ không ở Task: tuỳ chọn nhận thông báo là thuộc tính
 * của *người dùng*, và đợt 2–4 sẽ thêm loại của báo cáo, chấm công, đơn nghỉ.
 * Để ở Task thì miền Attendance sẽ phải gọi ngược sang Task chỉ để đọc một enum
 * — đúng thứ quy tắc phụ thuộc cấm.
 *
 * Mặc định chọn theo mức độ gấp, không bật hết mọi kênh: hộp thư đầy thông báo
 * là hộp thư không ai đọc, và lúc đó thông báo thật sự quan trọng cũng trôi qua.
 */
enum NotificationType: string
{
    case TaskAssigned = 'task.assigned';
    case TaskDueSoon = 'task.due_soon';
    case TaskOverdue = 'task.overdue';
    case CommentAdded = 'task.comment_added';
    case Mentioned = 'task.mentioned';

    /**
     * Quỹ thưởng dự án đã chốt.
     *
     * Loại đầu tiên không thuộc miền Task — enum này ở Identity chính vì đã
     * lường trước điều đó.
     */
    case BonusLocked = 'bonus.locked';

    /** Quản lý có nhận xét trên báo cáo ngày. */
    case ReportReviewed = 'report.reviewed';

    /** Cuối ngày mà chưa nộp báo cáo. */
    case ReportMissing = 'report.missing';

    /** Có nhân viên nộp đơn nghỉ cần duyệt. */
    case LeaveRequested = 'leave.requested';

    /** Đơn nghỉ của mình đã được duyệt hoặc bị từ chối. */
    case LeaveReviewed = 'leave.reviewed';
    case LateArrivalRequested = 'late_arrival.requested';
    case LateArrivalReviewed = 'late_arrival.reviewed';

    public function label(): string
    {
        return match ($this) {
            self::TaskAssigned => 'Được giao việc',
            self::TaskDueSoon => 'Việc sắp tới hạn',
            self::TaskOverdue => 'Việc đã quá hạn',
            self::CommentAdded => 'Có bình luận mới',
            self::Mentioned => 'Được nhắc tên',
            self::BonusLocked => 'Thưởng dự án đã chốt',
            self::ReportReviewed => 'Quản lý nhận xét báo cáo',
            self::ReportMissing => 'Nhắc nộp báo cáo ngày',
            self::LeaveRequested => 'Có đơn nghỉ cần duyệt',
            self::LeaveReviewed => 'Đơn nghỉ đã được xử lý',
            self::LateArrivalRequested => 'Có đơn xin đi muộn cần duyệt',
            self::LateArrivalReviewed => 'Đơn xin đi muộn đã được xử lý',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TaskAssigned => 'Khi có người giao việc mới cho bạn.',
            self::TaskDueSoon => 'Khi một việc của bạn còn dưới một ngày là tới hạn.',
            self::TaskOverdue => 'Khi một việc của bạn đã qua hạn mà chưa đóng.',
            self::CommentAdded => 'Khi có bình luận mới trên việc bạn đang theo dõi.',
            self::Mentioned => 'Khi ai đó nhắc tên bạn trong một bình luận.',
            self::BonusLocked => 'Khi quỹ thưởng của một dự án bạn tham gia được chốt.',
            self::ReportReviewed => 'Khi quản lý viết nhận xét hoặc hỏi lại về báo cáo ngày của bạn.',
            self::ReportMissing => 'Cuối giờ chiều, nếu hôm nay bạn có giờ làm mà chưa nộp báo cáo.',
            self::LeaveRequested => 'Khi nhân viên bạn quản lý nộp đơn xin nghỉ.',
            self::LeaveReviewed => 'Khi đơn xin nghỉ của bạn được duyệt hoặc bị từ chối.',
            self::LateArrivalRequested => 'Khi nhân viên bạn quản lý nộp đơn xin đi làm muộn.',
            self::LateArrivalReviewed => 'Khi đơn xin đi muộn của bạn được duyệt hoặc bị từ chối.',
        };
    }

    /** Thông báo trong ứng dụng — mặc định bật hết, đây là kênh rẻ và không phiền. */
    public function defaultInApp(): bool
    {
        return true;
    }

    /**
     * Email — chỉ bật mặc định cho những việc mà bỏ lỡ là có hậu quả thật.
     *
     * "Có bình luận mới" không nằm trong đó: một task sôi nổi có thể sinh vài
     * chục bình luận một ngày, và gửi email cho từng cái là cách nhanh nhất để
     * người dùng lọc toàn bộ thông báo của hệ thống vào thư rác.
     */
    public function defaultEmail(): bool
    {
        return match ($this) {
            // Thưởng chốt là việc hiếm và đáng biết ngay — bật email mặc định.
            //
            // Nhắc báo cáo cũng bật email, dù nghe như một lời càm ràm hằng
            // ngày. Lý do: nó bắn lúc 17h30, đúng lúc nhiều người đã đóng
            // trình duyệt — thông báo trong ứng dụng lúc đó không tới được ai,
            // và tính năng thành vô dụng với chính nhóm nó nhắm tới. Bù lại nó
            // chỉ gửi tối đa một lần mỗi người mỗi ngày, và chỉ khi thật sự có
            // việc phải làm.
            //
            // Hai loại của nghỉ phép cũng bật email: người nộp đơn đang CHỜ một
            // câu trả lời để sắp xếp việc riêng, và người duyệt cần biết có đơn
            // treo trước khi nhân viên đã nghỉ mất rồi. Cả hai đều hiếm — vài
            // lần một tháng — nên không có nguy cơ dội thư.
            self::TaskAssigned, self::TaskOverdue, self::Mentioned, self::BonusLocked,
            self::ReportMissing, self::LeaveRequested, self::LeaveReviewed,
            self::LateArrivalRequested, self::LateArrivalReviewed => true,
            // Nhận xét báo cáo là chuyện trao đổi hằng ngày, đọc trong ứng
            // dụng là đủ. Gửi email mỗi lần quản lý viết một câu thì hộp thư
            // đầy trong một tuần.
            self::TaskDueSoon, self::CommentAdded, self::ReportReviewed => false,
        };
    }
}
