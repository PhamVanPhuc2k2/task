<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mọi controller API phải khai quyền — hoặc được miễn CÓ LÝ DO
|--------------------------------------------------------------------------
|
| ## Vì sao cần luật này
|
| Hôm nay 33/54 controller có chặn quyền, và 21 cái còn lại đều có lý do đúng.
| Nhưng **không có gì giữ cho điều đó còn đúng vào tháng sau**.
|
| Ai thêm `DeleteProjectController` mà quên `#[Authorize]` thì:
|
|   - Deptrac không bắt — nó chỉ theo dõi phụ thuộc giữa các tầng
|   - Larastan không bắt — thiếu một attribute không phải lỗi kiểu
|   - Test không bắt — không ai viết test cho quyền mình không biết là thiếu
|
| Nó chỉ lộ ra khi có người thử. Đây đúng loại lỗi **hỏng im lặng** mà dự án
| này liên tục phải trả giá — cùng họ với ngày nghỉ không hiện trên bảng công,
| và với việc Larastan âm thầm bỏ qua migration.
|
| ## Luật
|
| Một controller trong `App\Http\Controllers\Api` là hợp lệ khi:
|
|   1. Có ít nhất một dấu hiệu kiểm quyền (`#[Authorize]`, `authorize()`,
|      `Gate::`, `->can()`, `abort_unless`, `abort_if`, `authorizeResource`), HOẶC
|   2. Nằm trong danh sách miễn trừ bên dưới, **kèm lý do viết ra thành chữ**
|
| Điểm mấu chốt là vế thứ hai. Miễn trừ vẫn được phép — nhiều controller thật
| sự không cần quyền — nhưng nó phải là một **hành động cố ý, có người gõ ra lý
| do**, chứ không phải hệ quả của việc quên.
|
| ## Không phải là bảo mật
|
| Luật này không chặn được gì. Nó chỉ bắt người viết mã dừng lại một nhịp và
| trả lời "vì sao chỗ này không cần quyền". Chặn thật nằm ở Policy và ở
| `#[Authorize]`; đây là lưới hứng cho thứ bị bỏ quên.
|
*/

/**
 * Controller được miễn khai quyền, và VÌ SAO.
 *
 * Thêm một dòng vào đây là một quyết định, không phải một thủ tục. Nếu bạn
 * đang thêm dòng mới chỉ để test hết đỏ thì hãy dừng lại và đọc lại lý do của
 * những dòng đã có — chúng đều thuộc đúng ba nhóm dưới đây.
 *
 * @var array<string, string>
 */
const MIEN_KHAI_QUYEN = [
    // ── Nhóm 1: luồng xác thực, bắt buộc phải công khai ──────────────────
    // Chưa đăng nhập thì chưa có ai để kiểm quyền. Chặn ở đây là khoá cửa từ
    // bên trong: không ai vào được nữa, kể cả người có chìa.
    'V1/Auth/LoginController.php' => 'Chưa đăng nhập thì chưa có người dùng để kiểm quyền.',
    'V1/Auth/LogoutController.php' => 'Ai đăng nhập cũng được tự thoát.',
    'V1/Auth/MeController.php' => 'Trả về chính người đang đăng nhập, không nhận tham số người dùng.',
    'V1/Auth/ChangePasswordController.php' => 'Đổi mật khẩu của chính mình; xác thực bằng mật khẩu cũ.',
    'V1/Auth/ForgotPasswordController.php' => 'Công khai theo thiết kế, có giới hạn tần suất theo email và IP.',
    'V1/Auth/ResetPasswordController.php' => 'Công khai; token đặt lại mật khẩu chính là thứ xác thực.',
    'V1/Auth/TwoFactorChallengeController.php' => 'Đang ở giữa luồng đăng nhập, phiên chưa hoàn tất.',
    'V1/Auth/TwoFactorResendController.php' => 'Cùng lý do: đang ở giữa luồng đăng nhập.',
    'V1/Auth/TwoFactorSetupController.php' => 'Bật 2FA cho chính mình.',
    'V1/Auth/TwoFactorConfirmController.php' => 'Xác nhận 2FA của chính mình.',
    'V1/HealthController.php' => 'Công khai theo thiết kế — bộ cân bằng tải gọi khi chưa có phiên nào.',
    'V1/Settings/SiteBrandingController.php' => 'Công khai theo thiết kế — trang đăng nhập cần tên và logo khi chưa có phiên nào. Chỉ trả nhận diện, không trả chính sách.',
    'V1/Settings/SiteFaviconController.php' => 'Công khai theo thiết kế — trình duyệt xin biểu tượng của tab trước cả trang đăng nhập, không kèm cookie nào. Chỉ chuyển hướng tới một ảnh, không đọc gì khác.',

    // ── Nhóm 2: chỉ thao tác trên dữ liệu của CHÍNH NGƯỜI ĐĂNG NHẬP ──────
    // Không có tham số người dùng trên đường dẫn, và trong mã chỉ đọc
    // `$request->user()`. Không có cách nào trỏ sang người khác.
    //
    // Đây là chỗ dễ hỏng nhất trong ba nhóm: chỉ cần một ngày ai đó thêm
    // `?user_id=` cho tiện là cả nhóm này thành lỗ hổng. Có test riêng kiểm
    // đúng điều đó — xem bên dưới.
    'V1/Attendance/MyAttendanceController.php' => 'Giờ làm của chính mình.',
    'V1/Attendance/HeartbeatController.php' => 'Ghi nhịp tim cho chính mình.',
    'V1/Attendance/MyAdjustmentController.php' => 'Đơn giải trình công của chính mình.',
    'V1/Attendance/SubmitAdjustmentController.php' => 'Giải trình ngày công của chính mình; đây là một lời khai, không khai hộ được.',
    'V1/Attendance/MyOvertimeController.php' => 'Đăng ký làm thêm giờ của chính mình.',
    'V1/Attendance/SubmitOvertimeController.php' => 'Đăng ký làm thêm cho chính mình; chữ ký nằm ở người sẽ ở lại làm.',
    'V1/Reports/MyReportsController.php' => 'Báo cáo ngày của chính mình.',
    'V1/Reports/SaveDailyReportController.php' => 'Lưu báo cáo của chính mình.',
    'V1/Tasks/MyTasksController.php' => 'Việc được giao cho chính mình.',
    'V1/Leave/MyLeaveController.php' => 'Đơn nghỉ của chính mình.',
    'V1/Leave/MyLeaveBalanceController.php' => 'Quỹ phép năm của chính mình.',
    'V1/Leave/SubmitLeaveController.php' => 'Nộp đơn nghỉ cho chính mình; không nộp hộ được.',
    'V1/Leave/MyLateArrivalController.php' => 'Đơn xin đi muộn của chính mình.',
    'V1/Leave/SubmitLateArrivalController.php' => 'Nộp đơn đi muộn cho chính mình; không nộp hộ được.',
    'V1/Notifications/NotificationSettingController.php' => 'Cài đặt thông báo của chính mình.',

    // ── Nhóm 3: danh mục chỉ đọc, ai đăng nhập cũng cần ──────────────────
    // Chỉ có `GET index`, không ghi. Là danh sách cơ cấu tổ chức mà mọi bộ lọc
    // trên giao diện đều cần. Gắn quyền vào đây thì người không có quyền sẽ
    // thấy bộ lọc rỗng mà không hiểu vì sao.
    'V1/Organization/DepartmentController.php' => 'Danh mục phòng ban, chỉ đọc.',
    'V1/Organization/PositionController.php' => 'Danh mục chức vụ, chỉ đọc.',
];

/** Dấu hiệu cho thấy controller có kiểm quyền. */
const DAU_HIEU_QUYEN = [
    '#[Authorize',
    'authorize(',
    'Gate::',
    '->can(',
    'denyIf',
    'abort_unless',
    'abort_if',
    'authorizeResource',
];

/** @return array<string, string> đường dẫn tương đối => nội dung tệp */
function controllerApi(): array
{
    $goc = dirname(__DIR__, 2).'/app/Http/Controllers/Api';

    $ds = [];

    /** @var iterable<SplFileInfo> $duyet */
    $duyet = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($goc));

    foreach ($duyet as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $ds[str_replace('\\', '/', substr($f->getPathname(), strlen($goc) + 1))]
                = (string) file_get_contents($f->getPathname());
        }
    }

    ksort($ds);

    return $ds;
}

it('mọi controller API đều khai quyền, hoặc được miễn có lý do', function (): void {
    $thieu = [];

    foreach (controllerApi() as $duongDan => $noiDung) {
        if (array_key_exists($duongDan, MIEN_KHAI_QUYEN)) {
            continue;
        }

        $coQuyen = false;

        foreach (DAU_HIEU_QUYEN as $dau) {
            if (str_contains($noiDung, $dau)) {
                $coQuyen = true;
                break;
            }
        }

        if (! $coQuyen) {
            $thieu[] = $duongDan;
        }
    }

    expect($thieu)->toBe([], sprintf(
        "Controller sau không kiểm quyền và cũng không được miễn:\n  - %s\n\n".
        'Thêm `#[Authorize]`, hoặc thêm vào MIEN_KHAI_QUYEN trong tệp này KÈM LÝ DO.',
        implode("\n  - ", $thieu),
    ));
});

it('danh sách miễn trừ không có dòng chết', function (): void {
    /*
    | Danh sách miễn trừ mục rữa theo thời gian: controller bị xoá hoặc đổi tên
    | thì dòng cũ nằm lại, và lần sau có người tạo file trùng tên sẽ được miễn
    | mà không ai để ý.
    */
    $coThat = array_keys(controllerApi());

    $thua = array_values(array_diff(array_keys(MIEN_KHAI_QUYEN), $coThat));

    expect($thua)->toBe([], sprintf(
        "Những dòng miễn trừ này trỏ tới tệp không còn tồn tại:\n  - %s",
        implode("\n  - ", $thua),
    ));
});

it('controller đã tự kiểm quyền thì không được nằm trong danh sách miễn trừ', function (): void {
    // Miễn trừ thừa còn tệ hơn thiếu: nó nói dối rằng chỗ đó không cần quyền,
    // và ngày ai đó xoá phần kiểm quyền đi thì không có gì báo.
    $thua = [];

    foreach (controllerApi() as $duongDan => $noiDung) {
        if (! array_key_exists($duongDan, MIEN_KHAI_QUYEN)) {
            continue;
        }

        foreach (DAU_HIEU_QUYEN as $dau) {
            if (str_contains($noiDung, $dau)) {
                $thua[] = $duongDan;
                break;
            }
        }
    }

    expect($thua)->toBe([], sprintf(
        "Những controller này đã tự kiểm quyền, bỏ khỏi MIEN_KHAI_QUYEN đi:\n  - %s",
        implode("\n  - ", $thua),
    ));
});

it('mọi lý do miễn trừ đều được viết ra tử tế', function (): void {
    // Không có mức sàn thì trường này đầy những dòng "không cần" — vẫn không ai
    // trả lời được câu "vì sao", mà lại tưởng là đã ghi. Cùng nguyên tắc với lý
    // do xin nghỉ và lý do duyệt ngày công.
    $soSai = [];

    foreach (MIEN_KHAI_QUYEN as $duongDan => $lyDo) {
        if (mb_strlen(trim($lyDo)) < 20) {
            $soSai[] = $duongDan;
        }
    }

    expect($soSai)->toBe([], sprintf(
        "Lý do miễn trừ quá ngắn để người khác hiểu được:\n  - %s",
        implode("\n  - ", $soSai),
    ));
});

it('controller "của chính tôi" không được nhận user_id từ client', function (): void {
    /*
    | Lưới an toàn cho Nhóm 2 của danh sách miễn trừ, và là test đáng giá nhất
    | trong tệp này.
    |
    | Những controller đó được miễn kiểm quyền CHỈ VÌ chúng luôn thao tác trên
    | `$request->user()`. Ngày nào có người thêm `?user_id=` cho tiện thì cả
    | nhóm biến thành lỗ hổng đọc dữ liệu người khác — mà danh sách miễn trừ
    | vẫn nói rằng chúng an toàn.
    */
    $nghiNgo = [];

    foreach (controllerApi() as $duongDan => $noiDung) {
        if (! str_contains($duongDan, 'My') && ! str_contains($duongDan, 'Submit')) {
            continue;
        }

        if (! array_key_exists($duongDan, MIEN_KHAI_QUYEN)) {
            continue;
        }

        if (preg_match("/(input|get|query|string|integer)\(\s*'user_id'/", $noiDung) === 1) {
            $nghiNgo[] = $duongDan;
        }
    }

    expect($nghiNgo)->toBe([], sprintf(
        'Controller được miễn quyền vì chỉ đụng dữ liệu của chính mình, nhưng lại '.
        "nhận `user_id` từ client:\n  - %s",
        implode("\n  - ", $nghiNgo),
    ));
});
