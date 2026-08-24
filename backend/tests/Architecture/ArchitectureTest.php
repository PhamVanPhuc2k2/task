<?php

declare(strict_types=1);
use Illuminate\Database\Eloquent\Model;

/*
|--------------------------------------------------------------------------
| Kiểm thử kiến trúc
|--------------------------------------------------------------------------
|
| Đây là phần biến chương "Kiến trúc & quy ước mã nguồn" trong README từ văn
| bản thành ràng buộc chạy được. CI fail ở đây là chặn merge.
|
| Quy ước không được máy kiểm tra thì sau vài tháng sẽ không còn ai theo.
|
*/

// ── Quy tắc phụ thuộc giữa các tầng ─────────────────

arch('Domain không biết tới tầng Http')
    ->expect('App\Domain')
    ->not->toUse([
        'Illuminate\Http\Request',
        'Illuminate\Http\Response',
        'Illuminate\Http\RedirectResponse',
        'Illuminate\Support\Facades\Auth',
        'Illuminate\Support\Facades\Session',
        'Illuminate\Support\Facades\Cookie',
        'Illuminate\Support\Facades\Redirect',
        'Illuminate\Support\Facades\Request',
    ]);

arch('Domain không dùng helper của tầng Http')
    ->expect(['request', 'auth', 'session', 'redirect', 'cookie', 'abort'])
    ->not->toBeUsedIn('App\Domain');

arch('Domain không phụ thuộc tầng Http')
    ->expect('App\Domain')
    ->not->toUse('App\Http');

arch('Support là tầng dưới cùng, không biết Domain và Http')
    ->expect('App\Support')
    ->not->toUse(['App\Domain', 'App\Http']);

// ── Ranh giới giữa các miền nghiệp vụ ───────────────
//
// Identity là "shared kernel" — mọi miền đều được tham chiếu tới nó
// (assignee_id, manager_id, department_id...). Đây là ngoại lệ duy nhất.
// Các miền còn lại muốn báo cho nhau thì bắn Event.

arch('Miền Task không gọi thẳng các miền nghiệp vụ khác')
    ->expect('App\Domain\Task')
    ->not->toUse([
        'App\Domain\Report',
        'App\Domain\Attendance',
        'App\Domain\Leave',
        'App\Domain\Payroll',
    ]);

arch('Miền Identity không phụ thuộc miền nào khác')
    ->expect('App\Domain\Identity')
    ->not->toUse([
        'App\Domain\Task',
        'App\Domain\Report',
        'App\Domain\Attendance',
        'App\Domain\Leave',
        'App\Domain\Payroll',
    ]);

// ── Tầng Http phải mỏng ─────────────────────────────

arch('Controller không truy vấn cơ sở dữ liệu trực tiếp')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Database\Query\Builder',
    ]);

// ── Quy ước chung ───────────────────────────────────

arch('Toàn bộ mã nguồn khai báo strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('Không sót hàm debug trong mã nguồn')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'ray', 'die', 'exit'])
    ->not->toBeUsed();

arch('Không dùng env() ngoài thư mục config')
    ->expect('env')
    ->not->toBeUsedIn(['App\Domain', 'App\Http', 'App\Support']);

// ── Quy ước đặt tên ─────────────────────────────────

arch('Action đặt tên kết thúc bằng Action và có đúng một điểm vào')
    ->expect('App\Domain\Identity\Actions')
    ->toHaveSuffix('Action')
    ->toHaveMethod('execute');

// Các luật cho App\Domain\Task\{Actions,Data,Jobs} bật lại ở mục 1.4 khi những
// namespace đó có class — Pest báo lỗi nếu expect() trỏ vào namespace rỗng.

arch('Enum phải là enum thật, không phải class hằng số')
    ->expect('App\Domain\Task\Enums')
    ->toBeEnums();

// Thư mục enum của tầng nền. Có luật riêng để nó không dần thành chỗ chứa tạp:
// Support là tầng dưới cùng, thứ nhét vào đây sẽ được cả hệ thống dùng chung.
arch('Enum dùng chung của tầng nền phải là enum thật')
    ->expect('App\Support\Enums')
    ->toBeEnums();

arch('Model chỉ nằm trong thư mục Models của từng miền')
    ->expect('App\Domain\Identity\Models')
    ->toExtend(Model::class)
    ->ignoring('App\Domain\Identity\Models\User');

arch('Model của miền Task đều là Eloquent model')
    ->expect('App\Domain\Task\Models')
    ->toExtend(Model::class);

// ── Bảo mật cơ bản ──────────────────────────────────

arch('Không dùng hàm nguy hiểm')
    ->expect(['eval', 'exec', 'shell_exec', 'system', 'passthru', 'unserialize'])
    ->not->toBeUsed();

// ── Bộ luật mặc định của Laravel ────────────────────
//
// Preset "laravel" giả định cấu trúc thư mục mặc định: ServiceProvider chỉ nằm
// ở App\Providers, model chỉ nằm ở App\Models. Kiến trúc modular của dự án này
// cố tình không theo — mỗi miền có ServiceProvider và Models riêng.
//
// Nên bỏ qua App\Domain ở preset này. Không phải nới lỏng: tầng Domain đã bị
// ràng buộc chặt hơn nhiều bởi các luật phía trên trong chính file này, cộng
// với Deptrac. Preset vẫn áp dụng đầy đủ cho App\Http, App\Providers, App\Support.

// App\Support\Exceptions cũng nằm ngoài preset: preset đòi mọi lớp Throwable
// phải ở App\Exceptions, nhưng DomainException là hạ tầng dùng chung cho mọi
// miền nên thuộc về Support. Xem chính file đó để biết lý do.

// App\Support\Enums: cùng một lý do với App\Domain. Preset đòi mọi enum nằm ở
// App\Enums, còn dự án này đặt enum cạnh tầng sở hữu nó —
// App\Domain\{Miền}\Enums cho enum nghiệp vụ, App\Support\Enums cho enum dùng
// chung mà không thuộc miền nào (ReportMatch bắc qua hai miền, HealthStatus là
// hạ tầng). Gom hết vào một thư mục App\Enums phẳng sẽ xoá đúng thông tin mà
// kiến trúc modular này muốn giữ. Đổi lại có luật `toBeEnums()` riêng phía trên
// để thư mục đó không thành chỗ chứa tạp.

// App\Support\Settings: chứa model `SiteSetting`, mà preset đòi mọi model nằm ở
// App\Models. Đây là lần đầu dự án có một model KHÔNG thuộc miền nghiệp vụ nào,
// nên phải chọn chỗ cho nó — và Support là chỗ đúng, vì hai lý do:
//
//   1. Cài đặt trang ghi đè config mà MỌI miền đều đọc. Đặt nó trong một miền
//      thì các miền khác phải gọi sang miền đó — đúng thứ quy tắc phụ thuộc của
//      dự án cấm. Support là tầng duy nhất ai cũng được phụ thuộc vào.
//   2. Cùng lý do `App\Support\Time\WorkDate` nằm ở đây: nó là hạ tầng dùng
//      chung, không phải nghiệp vụ.
//
// Ranh giới vẫn giữ: Deptrac chặn App\Support gọi sang App\Domain hoặc App\Http,
// nên tầng này không thể lặng lẽ phình thành nơi chứa nghiệp vụ.

arch()->preset()->laravel()->ignoring([
    'App\Domain',
    'App\Support\Enums',
    'App\Support\Exceptions',
    'App\Support\Settings',
]);

arch()->preset()->security();
