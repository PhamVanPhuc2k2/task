<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Attendance\AttendanceTimelineController;
use App\Http\Controllers\Api\V1\Attendance\CancelAdjustmentController;
use App\Http\Controllers\Api\V1\Attendance\ClosePeriodController;
use App\Http\Controllers\Api\V1\Attendance\HeartbeatController;
use App\Http\Controllers\Api\V1\Attendance\MyAdjustmentController;
use App\Http\Controllers\Api\V1\Attendance\MyAttendanceController;
use App\Http\Controllers\Api\V1\Attendance\PeriodController;
use App\Http\Controllers\Api\V1\Attendance\ReopenPeriodController;
use App\Http\Controllers\Api\V1\Attendance\ReviewAdjustmentController;
use App\Http\Controllers\Api\V1\Attendance\ReviewWorkDayController;
use App\Http\Controllers\Api\V1\Attendance\SubmitAdjustmentController;
use App\Http\Controllers\Api\V1\Attendance\TeamAdjustmentController;
use App\Http\Controllers\Api\V1\Attendance\TeamAttendanceController;
use App\Http\Controllers\Api\V1\Attendance\WorkDayDetailController;
use App\Http\Controllers\Api\V1\Auth\ChangePasswordController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorConfirmController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorResendController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorSetupController;
use App\Http\Controllers\Api\V1\Dashboard\OverviewController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Leave\CancelLateArrivalController;
use App\Http\Controllers\Api\V1\Leave\CancelLeaveController;
use App\Http\Controllers\Api\V1\Leave\LeaveBalanceController;
use App\Http\Controllers\Api\V1\Leave\MyLateArrivalController;
use App\Http\Controllers\Api\V1\Leave\MyLeaveBalanceController;
use App\Http\Controllers\Api\V1\Leave\MyLeaveController;
use App\Http\Controllers\Api\V1\Leave\ReviewLateArrivalController;
use App\Http\Controllers\Api\V1\Leave\ReviewLeaveController;
use App\Http\Controllers\Api\V1\Leave\SaveLeaveBalanceController;
use App\Http\Controllers\Api\V1\Leave\SubmitLateArrivalController;
use App\Http\Controllers\Api\V1\Leave\SubmitLeaveController;
use App\Http\Controllers\Api\V1\Leave\TeamLateArrivalController;
use App\Http\Controllers\Api\V1\Leave\TeamLeaveController;
use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\V1\Notifications\NotificationSettingController;
use App\Http\Controllers\Api\V1\Organization\DepartmentAdminController;
use App\Http\Controllers\Api\V1\Organization\DepartmentController;
use App\Http\Controllers\Api\V1\Organization\PositionController;
use App\Http\Controllers\Api\V1\Payroll\AllocateBonusController;
use App\Http\Controllers\Api\V1\Payroll\ChangeBonusPoolStatusController;
use App\Http\Controllers\Api\V1\Payroll\MyBonusController;
use App\Http\Controllers\Api\V1\Payroll\PayrollController;
use App\Http\Controllers\Api\V1\Payroll\ProjectBonusController;
use App\Http\Controllers\Api\V1\Projects\ProjectController;
use App\Http\Controllers\Api\V1\Projects\ProjectMemberController;
use App\Http\Controllers\Api\V1\Reports\MyReportsController;
use App\Http\Controllers\Api\V1\Reports\ReviewDailyReportController;
use App\Http\Controllers\Api\V1\Reports\SaveDailyReportController;
use App\Http\Controllers\Api\V1\Reports\TeamReportsController;
use App\Http\Controllers\Api\V1\Settings\SiteBrandingController;
use App\Http\Controllers\Api\V1\Settings\SiteFaviconController;
use App\Http\Controllers\Api\V1\Settings\SiteIconController;
use App\Http\Controllers\Api\V1\Settings\SiteLogoController;
use App\Http\Controllers\Api\V1\Settings\SiteSettingController;
use App\Http\Controllers\Api\V1\Tasks\AssignTaskController;
use App\Http\Controllers\Api\V1\Tasks\BulkReassignController;
use App\Http\Controllers\Api\V1\Tasks\ChangeTaskDueDateController;
use App\Http\Controllers\Api\V1\Tasks\ChangeTaskStatusController;
use App\Http\Controllers\Api\V1\Tasks\CommentAttachmentController;
use App\Http\Controllers\Api\V1\Tasks\MyTasksController;
use App\Http\Controllers\Api\V1\Tasks\TaskActivityController;
use App\Http\Controllers\Api\V1\Tasks\TaskCommentController;
use App\Http\Controllers\Api\V1\Tasks\TaskController;
use App\Http\Controllers\Api\V1\Tasks\TeamTasksController;
use App\Http\Controllers\Api\V1\Users\ActivateUserController;
use App\Http\Controllers\Api\V1\Users\AssignableUsersController;
use App\Http\Controllers\Api\V1\Users\DeactivateUserController;
use App\Http\Controllers\Api\V1\Users\ResetTwoFactorController;
use App\Http\Controllers\Api\V1\Users\ResetUserPasswordController;
use App\Http\Controllers\Api\V1\Users\UserActivityController;
use App\Http\Controllers\Api\V1\Users\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Tiền tố `api/v1` khai báo ở bootstrap/app.php, nên đường dẫn ở đây viết
| tương đối: '/auth/login' → '/api/v1/auth/login'.
|
| Middleware `active` chặn tài khoản bị vô hiệu hoá ngay giữa phiên, không chỉ
| lúc đăng nhập, và nạp sẵn vai trò/quyền cho request.
|
| Tham số {user} nhận uuid chứ không nhận id tuần tự — HasUuid đặt
| getRouteKeyName() = 'uuid'.
|
| Các thao tác không thuộc CRUD (đăng nhập, đăng xuất, vô hiệu hoá...) dùng
| controller một hành động với __invoke, thay vì gộp vào một controller lớn.
|
*/

/*
 * Tình trạng hạ tầng — cho hệ thống giám sát và bộ cân bằng tải.
 *
 * Không đăng nhập, có chủ ý: bộ giám sát không có tài khoản, và một phép kiểm
 * chỉ chạy được khi đăng nhập được thì vô dụng đúng lúc database sập. Phản hồi
 * không chứa gì nhạy cảm — xem HealthController.
 *
 * Hạn mức riêng, rộng hơn `throttle:api`: bộ giám sát gọi đều đặn từ cùng một
 * địa chỉ IP, và dùng chung bộ đếm với người dùng thật thì hoặc là nó ăn hết
 * hạn mức của người ta, hoặc là chính nó bị 429 rồi báo động giả.
 */
Route::get('/health', HealthController::class)
    ->middleware('throttle:120,1')
    ->withoutMiddleware('throttle:api');

/*
| Nhận diện công ty — CÔNG KHAI, có chủ ý.
|
| Trang đăng nhập cần tên và logo, mà lúc đó chưa có phiên nào. Đường này chỉ
| trả tên và logo; chính sách (ca làm, cửa sổ nộp đơn) nằm ở /settings và đòi
| quyền — xem SiteBrandingController để biết vì sao ranh giới đó quan trọng.
*/
Route::get('/site', SiteBrandingController::class)->middleware('throttle:120,1');

/*
| Biểu tượng trên tab trình duyệt — CÔNG KHAI, và luôn chuyển hướng tới một
| ảnh thật.
|
| Frontend khai URL này tĩnh trong `metadata.icons` vì `generateMetadata()`
| chạy trên máy chủ Next, nơi đường dẫn API tương đối của production không có
| origin để phân giải. Xem SiteFaviconController.
|
| Hạn mức rộng hơn /site: trình duyệt xin favicon ở mọi tab mới, và cả văn
| phòng thường đi ra Internet bằng chung một địa chỉ IP.
*/
Route::get('/site/icon', SiteFaviconController::class)->middleware('throttle:300,1');

Route::prefix('auth')->group(function (): void {
    // Bước một: email + mật khẩu. Chưa đăng nhập được — hệ thống bắt buộc
    // xác thực hai lớp với mọi tài khoản.
    Route::post('/login', LoginController::class);

    /*
     * Quên mật khẩu — hai bước, đều không cần đăng nhập.
     *
     * `forgot-password` LUÔN trả về cùng một câu dù email có tồn tại hay không:
     * trả lời khác nhau là biến nó thành công cụ dò danh sách nhân sự. Xem
     * ForgotPasswordController.
     *
     * Hạn mức riêng, chặt hơn `throttle:api` nhiều: đây là hai đường duy nhất
     * gửi email cho người chưa đăng nhập, nên cũng là hai đường dễ bị dùng để
     * dội thư rác vào hộp thư người khác.
     */
    Route::post('/forgot-password', ForgotPasswordController::class)
        ->middleware('throttle:20,1')
        ->withoutMiddleware('throttle:api');

    Route::post('/reset-password', ResetPasswordController::class)
        ->middleware('throttle:20,1')
        ->withoutMiddleware('throttle:api');

    // Bước hai. Chưa qua bước một thì các route này trả NO_PENDING_LOGIN.
    Route::post('/two-factor-challenge', TwoFactorChallengeController::class);
    Route::get('/two-factor/setup', TwoFactorSetupController::class);
    Route::post('/two-factor/confirm', TwoFactorConfirmController::class);
    Route::post('/two-factor/resend', TwoFactorResendController::class);

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('/me', MeController::class);
        Route::post('/logout', LogoutController::class);
        Route::patch('/password', ChangePasswordController::class);
    });
});

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);

    // Danh bạ rút gọn để vẽ ô chọn người. Khai báo TRƯỚC /users/{user}, và
    // tách khỏi /users vì đường kia đòi quyền quản trị nhân sự — trưởng phòng
    // giao được việc nhưng không quản trị người dùng.
    Route::get('/users/assignable', AssignableUsersController::class);

    // Cơ cấu tổ chức — dùng để vẽ ô chọn trong form nhân sự. Ai đăng nhập
    // cũng đọc được: đây là thông tin cả công ty vốn đã biết.
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/positions', [PositionController::class, 'index']);

    /*
    | Sửa cây phòng ban. Quyền `organization.manage` — admin và giám đốc.
    |
    | Tách khỏi `user.manage` có chủ ý: thêm một nhân viên ảnh hưởng tới một
    | người, còn chuyển một phòng ban sang nhánh khác đổi phạm vi nhìn của mọi
    | trưởng phòng trên đường đi — cùng lúc, ở 13 chỗ, không màn hình nào báo.
    | Lý do đầy đủ ở Permission::ManageOrganization.
    |
    | PUT chứ không PATCH, cùng lý do với /users/{user}.
    */
    Route::post('/departments', [DepartmentAdminController::class, 'store']);
    Route::put('/departments/{department}', [DepartmentAdminController::class, 'update']);
    Route::delete('/departments/{department}', [DepartmentAdminController::class, 'destroy']);

    // PUT chứ không PATCH: sửa hồ sơ nhân viên là thay thế toàn bộ, vì `null`
    // ở PATCH không phân biệt được "xoá quản lý trực tiếp" với "không đụng
    // tới". Lý do đầy đủ ở App\Domain\Identity\Data\UpdateUserData.
    Route::put('/users/{user}', [UserController::class, 'update']);

    Route::post('/users/{user}/deactivate', DeactivateUserController::class);
    Route::post('/users/{user}/activate', ActivateUserController::class);
    Route::post('/users/{user}/reset-password', ResetUserPasswordController::class);
    Route::post('/users/{user}/reset-two-factor', ResetTwoFactorController::class);

    // Nhật ký nhân sự: ai đổi gì của ai, lúc nào. Chỉ người quản trị nhân sự
    // đọc được — xem UserActivityController.
    Route::get('/users/{user}/activities', [UserActivityController::class, 'index']);

    /*
    | Cài đặt trang. Quyền `setting.manage` — admin và giám đốc.
    |
    | Đây là màn đổi được cách tính công của cả công ty, nên mọi thay đổi đều
    | ghi lại người sửa (cột `updated_by`).
    */
    Route::get('/settings', [SiteSettingController::class, 'index']);
    Route::put('/settings', [SiteSettingController::class, 'update']);
    Route::post('/settings/logo', [SiteLogoController::class, 'store']);
    Route::delete('/settings/logo', [SiteLogoController::class, 'destroy']);
    Route::post('/settings/icon', [SiteIconController::class, 'store']);
    Route::delete('/settings/icon', [SiteIconController::class, 'destroy']);

    // ── Chấm công ────────────────────────────────────
    // Nhịp tim là đường được gọi nhiều nhất hệ thống (~96.000 lượt/ngày với
    // 200 nhân sự). Nó cố tình làm ít việc: tìm phiên gần nhất, ghi, trả về
    // số phút hôm nay để giao diện khỏi hỏi thêm một lượt nữa.
    /*
     * Nhịp tim — đường được gọi nhiều nhất cả hệ thống.
     *
     * Hai trăm nhân sự × tám tiếng ≈ 96.000 lượt mỗi ngày. Nó KHÔNG kiểm quyền
     * nào (ai đăng nhập cũng gửi được nhịp của chính mình), nên khai để
     * middleware `active` bỏ qua bước nạp vai trò và quyền — ba truy vấn cho
     * một câu hỏi không ai đặt ra. Xem EnsureUserIsActive::canNapQuyen().
     */
    Route::post('/attendance/heartbeat', HeartbeatController::class)
        ->defaults('preload_permissions', false);
    Route::get('/attendance/me', MyAttendanceController::class);
    Route::get('/attendance/team', TeamAttendanceController::class);

    /*
    | Dòng thời gian một ngày: hôm nay ai đang làm, khoảng nào ngồi không.
    | Trả lời câu mà lưới tháng không trả lời được — tổng số giờ giống nhau
    | thì làm liền mạch và làm rải rác vẫn ra cùng một con số.
    */
    Route::get('/attendance/timeline', AttendanceTimelineController::class);

    /*
    | Đơn giải trình công — nhân viên tự nói ra vì sao một ngày đo thiếu.
    |
    | Trước đây `work_days` chỉ có một cửa vào: quản lý bấm nút. Người đi gặp
    | khách cả ngày phải nhắn Zalo, và lý do thật của một ngày công bất thường
    | nằm trong lịch sử chat của hai người. Từ khi có chốt sổ kỳ công thì chuyện
    | đó thành hạn chót cứng — chốt rồi là không ai duyệt được nữa.
    |
    | Dùng chung quyền `attendance.review` với nút bấm tay: duyệt một đơn giải
    | trình CHÍNH LÀ ra quyết định trên ngày công đó, chỉ khác ai khởi xướng.
    |
    | KHAI TRƯỚC /attendance/{user}/{date} — bắt buộc, không phải để cho đẹp.
    | `GET /attendance/adjustments/me` khớp đúng dạng {user}/{date}, nên đặt sau
    | thì Laravel hiểu "adjustments" là uuid người dùng và trả 404. Cùng cái bẫy
    | đã ghi ngay bên dưới cho /attendance/me.
    |
    | Tham số {adjustment} nhận uuid — HasUuid đặt getRouteKeyName() = 'uuid'.
    */
    Route::get('/attendance/adjustments/me', MyAdjustmentController::class);
    Route::get('/attendance/adjustments/team', TeamAdjustmentController::class);
    Route::post('/attendance/adjustments', SubmitAdjustmentController::class);
    Route::post('/attendance/adjustments/{adjustment}/review', ReviewAdjustmentController::class);
    Route::post('/attendance/adjustments/{adjustment}/cancel', CancelAdjustmentController::class);

    // Khai báo SAU /attendance/me và /attendance/team, nếu không Laravel hiểu
    // "me" là uuid người dùng rồi trả 404.
    Route::get('/attendance/{user}/{date}', WorkDayDetailController::class);
    Route::post('/attendance/{user}/review', ReviewWorkDayController::class);

    /*
    | Chốt sổ kỳ công — nền móng của mọi phép tính tiền ở đợt 4.
    |
    | Hai quyền tách nhau: `attendance.period.close` cho giám đốc và admin,
    | `attendance.period.reopen` CHỈ cho giám đốc. Chốt là việc hành chính cuối
    | kỳ; mở khoá là việc đụng vào số liệu đã dùng để trả lương.
    |
    | Khai TRƯỚC /attendance/{user}/{date}? Không cần — `periods` không khớp
    | dạng {user} vì route đó nhận uuid, nhưng khai ở đây cho gần nhóm chấm công.
    */
    Route::get('/attendance/periods', [PeriodController::class, 'index']);
    Route::post('/attendance/periods/close', ClosePeriodController::class);
    Route::post('/attendance/periods/reopen', ReopenPeriodController::class);

    // ── Báo cáo ngày ─────────────────────────────────
    // Một báo cáo mỗi người mỗi ngày. Màn của quản lý trả về CẢ người chưa nộp
    // — xem TeamReportsController.
    Route::get('/reports/me', MyReportsController::class);
    Route::post('/reports', SaveDailyReportController::class);
    Route::get('/reports/team', TeamReportsController::class);
    Route::post('/reports/{report}/review', ReviewDailyReportController::class);

    /*
     * Nghỉ phép.
     *
     * Cùng khuôn với chấm công và báo cáo: đường "của tôi" không đòi quyền gì,
     * đường "của đội" đòi `leave.view.team` hoặc `leave.view.all`.
     *
     * Tham số {leave} nhận uuid — HasUuid đặt getRouteKeyName() = 'uuid'.
     */
    /*
    | Quỹ phép năm.
    |
    | Khai TRƯỚC /leave/{leave}/... cho gần nhóm đọc, và vì "balances" là một
    | đoạn cố định: đặt lẫn vào giữa các route có tham số là mời người sau đọc
    | nhầm thứ tự ưu tiên.
    |
    | Ba đường, ba mức quyền khác nhau:
    |   - /leave/balance    quỹ của CHÍNH MÌNH, không cần quyền nào
    |   - /leave/balances   bảng của phòng, cần leave.view.team hoặc .all
    |   - POST .../{user}   SỬA quỹ, cần leave.balance.manage — quyền riêng, vì
    |                       cộng thêm ngày phép là quyết định ra tiền
    */
    Route::get('/leave/balance', MyLeaveBalanceController::class);
    Route::get('/leave/balances', [LeaveBalanceController::class, 'index']);
    Route::post('/leave/balances/{user}', SaveLeaveBalanceController::class);

    Route::get('/leave/me', MyLeaveController::class);
    Route::post('/leave', SubmitLeaveController::class);
    Route::get('/leave/team', TeamLeaveController::class);
    Route::post('/leave/{leave}/cancel', CancelLeaveController::class);
    Route::post('/leave/{leave}/review', ReviewLeaveController::class);

    /*
    | Đơn xin đi làm muộn.
    |
    | Bảng riêng chứ không phải một loại nghỉ phép: đơn nghỉ đo bằng NGÀY, đơn
    | này đo bằng GIỜ. Nhưng dùng chung quyền `leave.approve` — người duyệt là
    | cùng một người, và tách quyền ra chỉ tạo ra tình huống ai đó duyệt được
    | loại này mà không duyệt được loại kia.
    |
    | Tham số {lateArrival} nhận uuid — HasUuid đặt getRouteKeyName() = 'uuid'.
    */
    Route::get('/late-arrivals/me', MyLateArrivalController::class);
    Route::post('/late-arrivals', SubmitLateArrivalController::class);
    Route::get('/late-arrivals/team', TeamLateArrivalController::class);
    Route::post('/late-arrivals/{lateArrival}/cancel', CancelLateArrivalController::class);
    Route::post('/late-arrivals/{lateArrival}/review', ReviewLateArrivalController::class);

    // ── Lương ────────────────────────────────────────
    // Tách hẳn khỏi /users, có chủ ý: quản trị nhân sự và quản trị lương là hai
    // vai khác nhau, nên guard cũng phải tách. Xem PayrollController.
    Route::get('/payroll', [PayrollController::class, 'index']);
    Route::get('/payroll/{user}', [PayrollController::class, 'show']);
    Route::post('/payroll/{user}', [PayrollController::class, 'store']);

    // ── Thưởng dự án ─────────────────────────────────
    // Không có endpoint phạt, và cố ý không có: Điều 127 Bộ luật Lao động 2019
    // cấm phạt tiền. Xem ProjectBonusController.
    Route::get('/bonus/me', MyBonusController::class);
    Route::get('/projects/{project}/bonus', [ProjectBonusController::class, 'show']);
    Route::post('/projects/{project}/bonus', [ProjectBonusController::class, 'store']);
    Route::put('/projects/{project}/bonus/allocations', AllocateBonusController::class);
    Route::post('/projects/{project}/bonus/status', ChangeBonusPoolStatusController::class);

    // ── Tổng quan ────────────────────────────────────
    // Chỉ người có task.view.all. Gom sẵn ở database vì cộng dồn từ /tasks/team
    // (phân trang 25 dòng) sẽ ra số sai — xem OverviewController.
    Route::get('/dashboard/overview', OverviewController::class);

    // ── Công việc ────────────────────────────────────
    // Mọi truy vấn đi qua scope visibleTo — ràng buộc bảo mật, không phải bộ
    // lọc tiện ích. Tham số {task} nhận uuid, không nhận id tuần tự.
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);

    // Khai báo TRƯỚC /tasks/{task}, nếu không Laravel sẽ hiểu "my" và "team"
    // là uuid rồi trả 404.
    Route::get('/tasks/my', MyTasksController::class);
    Route::get('/tasks/team', TeamTasksController::class);
    Route::post('/tasks/bulk-reassign', BulkReassignController::class);

    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::patch('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

    // Hành động riêng, tách khỏi PATCH chung vì mỗi cái có luật riêng:
    // đổi trạng thái phải hợp luồng, giao lại cần quyền, dời hạn phải có lý do.
    Route::patch('/tasks/{task}/status', ChangeTaskStatusController::class);
    Route::patch('/tasks/{task}/assign', AssignTaskController::class);
    Route::patch('/tasks/{task}/due-date', ChangeTaskDueDateController::class);

    // Dòng thời gian hoạt động, dùng cho trang chi tiết task.
    Route::get('/tasks/{task}/activities', [TaskActivityController::class, 'index']);

    // ── Bình luận ────────────────────────────────────
    // Đọc và viết đi theo quyền xem task; sửa và xoá theo TaskCommentPolicy.
    // Tham số {comment} nhận uuid.
    Route::get('/tasks/{task}/comments', [TaskCommentController::class, 'index']);
    Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store']);
    Route::patch('/comments/{comment}', [TaskCommentController::class, 'update']);
    Route::delete('/comments/{comment}', [TaskCommentController::class, 'destroy']);

    // Tách khỏi đường viết bình luận vì đây là multipart, còn kia là JSON.
    Route::post('/comments/{comment}/attachments', [CommentAttachmentController::class, 'store']);
    Route::delete('/comments/{comment}/attachments/{media}', [CommentAttachmentController::class, 'destroy']);

    // ── Thông báo ────────────────────────────────────
    // Luôn của chính người đang đăng nhập, không có tham số người dùng.
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'show']);
    Route::post('/notifications/read-all', [NotificationController::class, 'store']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'update']);

    Route::get('/notification-settings', [NotificationSettingController::class, 'index']);
    Route::patch('/notification-settings', [NotificationSettingController::class, 'update']);

    // ── Dự án ────────────────────────────────────────
    // Cũng đi qua scope visibleTo riêng của Project: dự án lộ tên khách hàng
    // và kế hoạch kinh doanh, mặc định là không thấy.
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::patch('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

    Route::get('/projects/{project}/members', [ProjectMemberController::class, 'index']);
    Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store']);
    Route::delete('/projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy']);
});
