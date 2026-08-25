# Explus — Hệ thống Quản lý Công việc & Nhân sự

Ứng dụng nội bộ của **Explus**: quản lý task, deadline, báo cáo tiến độ hằng ngày, chấm công và đơn từ cho nhân viên làm việc từ xa.

---

## Trạng thái hiện tại

**706 test xanh** (4731 assertions) · Larastan mức 8 sạch · Deptrac 0 vi phạm · `composer audit` & `npm audit` sạch · ESLint / Prettier / `tsc` / `next build` đều xanh

24 model · 25 migration · 44 bảng · 69 endpoint API · 26 quyền · 4 vai trò · giao diện và thông báo lỗi hoàn toàn tiếng Việt

### Chạy được rồi

| Hạng mục | Trạng thái |
|---|---|
| **Hạ tầng** — Docker, PHP 8.4, Laravel 13.24, Next.js 16, MySQL 8.4, Redis, Horizon, Mailpit | ✅ Mục [1.1](#11-khởi-tạo-dự-án--đã-xong) |
| **Khung kiến trúc** — Modular Monolith, Deptrac, 17 architecture test | ✅ Mục [1.1](#11-khởi-tạo-dự-án--đã-xong) |
| **Đăng nhập** — hai bước, cookie httpOnly, rate limit, nhật ký | ✅ Mục [1.2](#12-xác-thực--phân-quyền) |
| **Xác thực hai lớp** — mã OTP qua email, bắt buộc mọi nhân viên | ✅ Mục [1.2b](#12b-xác-thực-hai-lớp-otp--đã-xong) |
| **Vai trò & quyền** — 4 vai trò, 12 quyền, Policy theo bản ghi | ✅ Mục [1.2](#12-xác-thực--phân-quyền) |
| **Quản trị nhân sự** — thêm và sửa nhân viên, đổi phòng ban / chức vụ / vai trò, tìm và lọc, vô hiệu hoá và kích hoạt lại, đặt lại mật khẩu, gỡ 2FA | ✅ Mục [1.2](#12-xác-thực--phân-quyền) + [Nhân sự](#quản-trị-nhân-sự--đã-xong) |
| **Nhật ký nhân sự** — ai đổi gì của ai, lúc nào; chỉ ghi thêm | ✅ [Nhật ký nhân sự](#sửa-hồ-sơ-kích-hoạt-lại-và-nhật-ký-nhân-sự) |
| **Trang Tổng quan** — số liệu toàn công ty, tải việc theo người, tiến độ dự án | ✅ [Tổng quan](#trang-tổng-quan--đã-xong) |
| **Báo cáo ngày** — nhân viên kể việc đã làm, quản lý đọc cả phòng kể cả người chưa nộp | ✅ Đợt [2](#đợt-2--báo-cáo-tiến-độ-hằng-ngày--phần-chữ-đã-xong) |
| **Chấm công** — giờ làm suy ra từ tương tác thật, bảng công tháng, duyệt kèm lý do | ✅ Đợt [3](#đợt-3--chấm-công--phần-đo-đã-xong) |
| **Mức lương** — lịch sử theo khoảng hiệu lực, quyền riêng, nhật ký cả việc xem | ✅ [Mức lương](#mức-lương--phần-đặt-và-xem-đã-xong) |
| **Thưởng dự án** — quỹ có điều kiện, chia kèm lý do, không có khoản phạt | ✅ [Thưởng dự án](#thưởng-dự-án--quỹ-và-chia-thủ-công-đã-xong) |
| **Mô hình dữ liệu** — 21 model, 24 migration, 42 bảng: nhân sự + dự án + task + chấm công + lương + thưởng | ✅ Mục [1.3](#13-mô-hình-dữ-liệu--đã-xong) |
| **Nhập nhân viên từ CSV** — `php artisan users:import` | ✅ Mục [1.3](#13-mô-hình-dữ-liệu--đã-xong) |
| **API Task** — CRUD, lọc, đổi trạng thái, giao lại, dời hạn, việc của tôi / của đội, bàn giao hàng loạt | ✅ Mục [1.4](#14-api-task--đã-xong) |
| **API Dự án** — CRUD, thành viên và vai trò trong dự án | ✅ Mục [1.4](#14-api-task--đã-xong) |
| **Nhật ký thay đổi task** — Observer tự ghi, không phụ thuộc người viết mã nhớ gọi | ✅ Mục [1.4](#14-api-task--đã-xong) |
| **Giao diện đăng nhập** — 4 bước, nhận diện Explus | ✅ Mục [1.7](#17-giao-diện--đã-xong) |
| **Khung ứng dụng** — thanh bên, ngăn kéo trên điện thoại, điều hướng theo quyền | ✅ Mục [1.7](#17-giao-diện--đã-xong) |
| **Màn hình "Hôm nay của tôi"** — gom việc theo hạn: quá hạn / hôm nay / tuần này | ✅ Mục [1.7](#17-giao-diện--đã-xong) |
| **Danh sách công việc** — lọc, tìm, phân trang; bảng trên máy tính, thẻ trên điện thoại | ✅ Mục [1.7](#17-giao-diện--đã-xong) |
| **Bảng Kanban** — kéo thả đổi trạng thái, kèm đường đi không cần kéo | ✅ Mục [1.7](#17-giao-diện--đã-xong) |
| **Chi tiết công việc** — dòng thời gian, đổi trạng thái, giao lại, dời hạn kèm lý do | ✅ Mục [1.7](#17-giao-diện--đã-xong) |
| **Trang dự án** — danh sách, chi tiết, thành viên và vai trò | ✅ Mục [1.7](#17-giao-diện--đã-xong) |
| **Bình luận & trao đổi** — trả lời, sửa, xoá, nhắc tên `@`, tệp đính kèm kèm thumbnail | ✅ Mục [1.5](#15-bình-luận--trao-đổi--đã-xong) |
| **Thông báo** — chuông, trung tâm thông báo, email, tự bật/tắt từng loại | ✅ Mục [1.6](#16-deadline--nhắc-việc--đã-xong) |
| **Nhắc deadline** — job nền quét việc sắp tới hạn và quá hạn, chạy theo lịch | ✅ Mục [1.6](#16-deadline--nhắc-việc--đã-xong) |
| **Giao diện làm lại** — nền trung tính có chiều sâu, icon điều hướng, avatar màu theo tên | ✅ Mục [1.7](#17-giao-diện--đã-xong) |
| **Mã OTP gửi qua hàng đợi** — đăng nhập từ ~4,3 giây xuống ~0,5 giây | ✅ Mục [1.2b](#12b-xác-thực-hai-lớp-otp--đã-xong) |
| **Presigned URL cho tệp đính kèm** — chọn kiểu URL theo ổ đĩa giữ tệp | ✅ [Đường dẫn tệp đính kèm](#đường-dẫn-tệp-đính-kèm-và-cloudflare-r2) |
| **Nhắc nộp báo cáo 17h30** — chỉ nhắc người hôm nay thật sự có giờ làm | ✅ [Đợt 2](#nhắc-nộp-báo-cáo-cuối-ngày) |
| **Đối chiếu giờ công với báo cáo ngày** — bốn tình huống có tên, chỉ một cần người nhìn | ✅ [Đợt 3](#đối-chiếu-giờ-công-với-báo-cáo-ngày) |
| **Health check hạ tầng** — `GET /api/v1/health`, ba mức ok/degraded/down | ✅ [Kiểm tra tình trạng hạ tầng](#kiểm-tra-tình-trạng-hạ-tầng) |

### Chưa làm

Còn lại của đợt 1 là **[mục 1.10 Vận hành](#110-vận-hành--đưa-vào-sử-dụng)** — staging, deploy không gián đoạn, sao lưu. Kèm bốn việc đã chuyển sang đó có chủ ý:

- **Service worker cho PWA** — manifest đã có, cài lên màn hình chính được rồi
- **Bật `MEDIA_DISK=r2`** — presigned URL đã làm xong và bucket đã kết nối được; chờ cấp lại token có quyền ghi
- **Test trình duyệt thật** (Playwright) — cần môi trường staging
- **Rà soát Nghị định 13/2023/NĐ-CP** và chính sách lưu trữ dữ liệu người đã nghỉ việc — xem [mục 1.9](#19-bảo-mật--đã-xong)

Sau 1.10, mở sang **đợt 2 — báo cáo tiến độ hằng ngày**.

### Thiếu sót đã biết ✅ Đã sửa hết

Năm thiếu sót ghi ra ở vòng rà soát trước, nay đã xử lý xong. Giữ lại bảng này vì mỗi dòng là một bài học về loại lỗi dễ lọt.

| Thiếu sót | Đã làm gì |
|---|---|
| **Thông báo lỗi validate bằng tiếng Anh** | Thêm `lang/vi` (validation, auth, passwords) + 4 test khoá |
| **Chốt quỹ thưởng không báo cho nhân viên** | `BonusLockedNotification` + 4 test |
| **`/bonus` chỉ nạp 25 dự án, cắt im lặng** | Nâng lên 100 và **hiện rõ khi vẫn còn bị cắt** |
| **Danh bạ chọn người cắt im lặng ở 100** | Trả thêm `meta.truncated` + `DirectoryHint` hiện lên giao diện |
| **Không có lối vào quỹ thưởng từ trang dự án** | Thẻ *Quỹ thưởng* ngay cột phải trang chi tiết dự án |

#### Ba điều rút ra

**`APP_LOCALE=vi` đã đặt từ đầu — thiếu là thư mục `lang/`.** Laravel âm thầm rơi về bản tiếng Anh trong vendor suốt nhiều đợt mà không cảnh báo gì. Đây là loại lỗi cấu hình *đúng nhưng không đủ*: biến môi trường nhìn thì hợp lý, nên không ai nghĩ tới việc kiểm. Đã đổi mặc định trong `config/app.php` thành `vi` để một môi trường thiếu biến cũng không rơi về tiếng Anh, và thêm test khoá.

**Bản dịch tự nó cũng có hai lỗi, chỉ lộ khi chạy thật.** Câu `unique` ra *"email này đã có người dùng."* — viết thường đầu câu, vì `:attribute` không tự viết hoa; đã đổi sang `:Attribute` (Laravel có sẵn biến thể ucfirst, an toàn với tiếng Việt có dấu). Và `phone` **không được dịch** vì `StoreUserRequest` khai bảy trường nhưng bỏ sót nó — nên đã thêm lưới an toàn `attributes` dùng chung ở `lang/vi/validation.php`. Bản đầu tôi cố ý để trống chỗ đó với lập luận "mỗi FormRequest tự khai"; lập luận đó sai, vì bắt mọi FormRequest nhớ mọi trường là điều chắc chắn sẽ hỏng.

**Cắt im lặng xuất hiện ở ba chỗ khác nhau** — 25 dự án, 100 người, và trước đó là bảng tổng quan. Cùng một khuôn mẫu: một `limit()` hợp lý về kỹ thuật, không có gì nói cho người dùng biết. Quy ước từ nay: **`limit` nào cũng phải đi kèm tổng số, và giao diện phải nói ra khi tổng lớn hơn phần hiện.**

**Thông báo thưởng cố ý không chứa số tiền.** Nó gửi cả qua email; số tiền thưởng nằm trong hộp thư cá nhân là một bản sao dữ liệu nhạy cảm ngoài tầm kiểm soát. Thông báo chỉ dẫn người dùng vào `/bonus`, nơi có cả số tiền lẫn lý do — mà số tiền tách khỏi lý do là mời người ta so bì trước khi hiểu. Người được 0 đồng vẫn nhận thông báo: im lặng với riêng nhóm đó là cách chắc chắn nhất để sinh tin đồn.

⚠️ **Tệp đính kèm vẫn đang lưu ở đĩa `public` của máy chủ.** Bucket R2 `explus-media` đã tạo, thông tin đã điền vào `backend/.env`, và kết nối đã kiểm chứng thật — nhưng token hiện tại **chỉ có quyền Object Read**: `headBucket` và `listObjectsV2` xanh, còn `putObject` trả `AccessDenied`. Cấp lại token quyền **Object Read & Write** rồi mới đổi `MEDIA_DISK=r2`. Phần code không còn chờ gì: xem [Đường dẫn tệp đính kèm và Cloudflare R2](#đường-dẫn-tệp-đính-kèm-và-cloudflare-r2).

Đã **chạy thử thật qua trình duyệt và Nginx**, không chỉ qua test: lấy CSRF → đăng nhập → nhận mã OTP trong hộp thư → xác nhận → vào được hệ thống với đúng vai trò.

✅ **Email đã gửi thật qua SMTP Gmail.** Mailpit vẫn chạy ở `localhost:8025` nhưng không còn nhận thư — đổi `MAIL_HOST` trong `backend/.env` nếu muốn quay lại hộp thư giả khi dev. Xem [Cấu hình gửi email](#cấu-hình-gửi-email).

---

## Bắt đầu nhanh

Cần Docker Desktop. Không cần cài PHP hay Composer trên máy — mọi lệnh backend chạy trong container PHP 8.4.

```bash
# 1. Chuẩn bị biến môi trường
cp .env.example .env
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env.local

# 2. Khởi động hạ tầng
docker compose up -d

# 3. Backend
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate --seed   # kèm cơ cấu tổ chức + tài khoản admin

# (tuỳ chọn) dữ liệu demo để có thứ nhìn được khi dev
docker compose run --rm app php artisan db:seed --class=DemoDataSeeder

# 4. Frontend
cd frontend && npm install && npm run dev
```

| Địa chỉ | Là gì |
|---|---|
| `http://localhost:3000` | Ứng dụng — nơi bạn đăng nhập |
| `http://localhost:8000` | API backend |
| **`http://localhost:8025`** | **Mailpit — đọc mã OTP ở đây khi dev** |
| `http://localhost:8000/horizon` | Theo dõi hàng đợi |
| `http://localhost:8000/docs/api` | Tài liệu API — xem [mục 1.8](#18-kiểm-thử--triển-khai--đã-xong) |

📖 **Hướng dẫn cho người dùng cuối:** [`docs/huong-dan-su-dung.md`](docs/huong-dan-su-dung.md) — đăng nhập, trạng thái công việc, nhắc tên, thông báo, cài lên điện thoại.

**Lần đầu đăng nhập:** hệ thống bắt buộc xác thực hai lớp, nên bạn sẽ được yêu cầu thiết lập. Mã gửi vào Mailpit chứ không vào hộp thư thật — mở `localhost:8025`, mã 6 số nằm ngay tiêu đề email.

Mật khẩu tài khoản quản trị đầu tiên do `AdminUserSeeder` sinh ngẫu nhiên và **in ra màn hình đúng một lần** lúc chạy `migrate --seed`. Đặt sẵn `ADMIN_PASSWORD` trong `backend/.env` nếu muốn chủ động.

**Chạy các cổng chất lượng trước khi tạo pull request:**

```bash
# Backend
docker compose run --rm app ./vendor/bin/pint            # định dạng
docker compose run --rm app ./vendor/bin/phpstan analyse # phân tích tĩnh mức 8
docker compose run --rm app ./vendor/bin/deptrac analyse # ranh giới kiến trúc
docker compose run --rm app ./vendor/bin/pest            # test + architecture test
docker compose run --rm app composer audit               # lỗ hổng phụ thuộc

# Frontend
cd frontend && npm run check                             # eslint + prettier + tsc
cd frontend && npm audit --audit-level=high              # lỗ hổng phụ thuộc
```

Sau khi đổi endpoint, sinh lại tài liệu API và kiểu TypeScript:

```bash
docker compose exec app php artisan scramble:export
cd frontend && npm run api:types
```

> **Backend chậm khi dev?** Xem [Vì sao backend chậm khi dev trên Windows](#vì-sao-backend-chậm-khi-dev-trên-windows) — nguyên nhân là lớp chia sẻ tệp của Docker Desktop, không phải mã nguồn.

Lần đầu chạy test cần tạo database riêng:

```bash
docker compose exec mysql mysql -uroot -proot \
  -e "CREATE DATABASE IF NOT EXISTS task_management_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Vì sao backend chậm khi dev trên Windows

Nếu mỗi lần F5 phải chờ 1,5–3 giây, đây là lý do — và nó **không phải lỗi mã nguồn**.

OPcache mặc định kiểm dấu thời gian của mọi file đã nạp ở mỗi request, để biết có file nào vừa sửa. Một request Laravel nạp khoảng **670 file**. Chi phí của việc kiểm đó phụ thuộc hoàn toàn vào việc mã nguồn nằm ở đâu — đo trên chính máy dev này:

| Mã nguồn nằm ở | `stat` 670 file |
|---|---|
| Bind mount từ ổ Windows (`D:\Projects\task`) | **~900 ms** |
| Ổ đĩa Linux của container | **1 ms** |

Gần **900 lần**. Docker Desktop phải chuyển từng lời gọi hệ thống qua lớp chia sẻ tệp giữa Windows và Linux, và `stat` là loại lời gọi chịu thiệt nặng nhất.

**Ba cách xử lý, kèm số đo thật:**

| Cách | Mỗi request | Đánh đổi |
|---|---|---|
| Để nguyên | 1,5–3 s | Không phải làm gì |
| `OPCACHE_VALIDATE_TIMESTAMPS=0` | **~0,10 s** | Sửa mã PHP xong phải nạp lại php-fpm |
| Chuyển mã nguồn vào hệ tệp WSL2 | **~0,10 s** | Không đánh đổi gì — nhưng phải di chuyển thư mục dự án |

```bash
# Cách 2 — bật chế độ nhanh
OPCACHE_VALIDATE_TIMESTAMPS=0 docker compose up -d app

# ...và sau mỗi lần sửa mã PHP:
docker compose exec app kill -USR2 1
```

Mặc định trong `.env.example` để `1` — an toàn, sửa mã là thấy ngay: một môi trường mà **code sửa xong không có hiệu lực và không báo gì** còn tệ hơn chờ 1,5 giây. Máy dev hiện tại đang đặt `0` trong `.env`; ai chủ yếu sửa mã PHP thì nên đổi lại thành `1`.

> **Cách 3 là cách đúng.** Chép dự án vào trong WSL2 (ví dụ `\\wsl$\Ubuntu\home\ban\task`) rồi chạy `docker compose` từ đó: nhanh như cách 2, mà vẫn giữ được việc sửa mã thấy ngay. Đây là hạn chế đã biết của Docker Desktop trên Windows, không phải của dự án.

⚠️ **Đổi tên hoặc di chuyển class thì phải chạy thêm hai lệnh** — và đây là loại lỗi mà **test không bắt được**:

```bash
docker compose exec app composer dump-autoload
docker compose restart app
```

Test chạy trong tiến trình mới với bảng ánh xạ class mới sinh, nên vẫn xanh. Ứng dụng thật thì đang giữ bảng cũ trong OPcache và trả `Class ... not found` — 500 trên mọi request chạm tới class đó. Đã dính đúng lỗi này khi chuyển `HealthStatus` sang `App\Support\Enums`: 524 test xanh, mà `curl /api/v1/health` trả 500.

**Vấn đề thứ hai: khung ứng dụng chặn cả trang để chờ `/auth/me`.**

Bản đầu của `AppShell` trả về một ô vuông 40px giữa màn hình trắng cho tới khi biết người dùng là ai. Hỏng theo hai cách cùng lúc:

1. Người dùng nhìn thấy **trang trắng tinh** — trông như hỏng, không như đang tải.
2. `children` chưa được gắn vào cây React nên truy vấn của trang chưa chạy. Hai lượt gọi **nối đuôi nhau**: `/auth/me` xong mới tới `/users`. Đo được: 3 request nối đuôi mất 4110 ms, chạy song song chỉ 1384 ms.

Đã sửa: khung, thanh bên và nội dung vẽ ngay; chỉ những chỗ thực sự cần dữ liệu người dùng mới hiện khung xương. Kết quả sau khi gộp cả hai cách sửa:

| Bước khi F5 | Thời gian |
|---|---|
| HTML, khung hiện ngay | 85 ms |
| 4 lượt gọi API chạy song song | 297 ms |

**Đã thử và loại bỏ:**

- `opcache.revalidate_freq = 2` — chỉ nhanh khi các request bắn dồn sát nhau. Đo lại theo nhịp dùng thật (bấm cách nhau vài giây) thì vẫn 1,6–3,5 s, vì mỗi request đều rơi ra ngoài cửa sổ 2 giây. Nửa vời không giải quyết được gì.
- `php artisan optimize` — bớt được khoảng 0,3 s trong tổng 1,6 s, nhưng khiến `.env` không còn hiệu lực cho tới khi cache lại. Không đáng cho môi trường dev; để dành cho production.

### Bố cục thư mục

```
.
├── backend/          # Laravel 13 — mọi đường dẫn app/... trong tài liệu này
│   │                 # đều tính từ đây
├── frontend/         # Next.js 16
├── docker/           # Dockerfile PHP, cấu hình Nginx
├── .github/workflows # CI
└── docker-compose.yml
```

---

## Mục lục

- [Trạng thái hiện tại](#trạng-thái-hiện-tại)
- [Bắt đầu nhanh](#bắt-đầu-nhanh)
- [Công nghệ](#công-nghệ)
- [Cấu hình gửi email](#cấu-hình-gửi-email)
- [Kiến trúc & quy ước mã nguồn](#kiến-trúc--quy-ước-mã-nguồn)
- [Lộ trình theo đợt](#lộ-trình-theo-đợt)
- [Đợt 1 — Quản lý Task](#đợt-1--quản-lý-task-đang-làm)
  - [1.9 Bảo mật](#19-bảo-mật--đã-xong)
  - [1.10 Vận hành & đưa vào sử dụng](#110-vận-hành--đưa-vào-sử-dụng)
  - [Quản trị nhân sự](#quản-trị-nhân-sự--đã-xong)
  - [Trang Tổng quan](#trang-tổng-quan--đã-xong)
- [Chấm công (đợt 3)](#đợt-3--chấm-công--phần-đo-đã-xong)
- [Mức lương](#mức-lương--phần-đặt-và-xem-đã-xong)
- [Thưởng dự án](#thưởng-dự-án--quỹ-và-chia-thủ-công-đã-xong)
- [Các đợt tiếp theo](#các-đợt-tiếp-theo)
- [Quyết định kiến trúc](#quyết-định-kiến-trúc-đã-chốt)
- [Câu hỏi còn mở](#câu-hỏi-còn-mở)

---

## Công nghệ

| Thành phần | Bản đang dùng | Ghi chú |
|---|---|---|
| Backend | **Laravel 13.24** | Phát hành 17/03/2026. Bug fix tới Q3/2027, security tới 03/2028 |
| PHP | **8.4** | Laravel 13 chấp nhận 8.3, nhưng bộ package đang dùng yêu cầu 8.4 — xem [lưu ý](#lưu-ý-về-các-package) |
| Frontend | **Next.js 16.3** (App Router) | React 19.2, Tailwind 4. SPA gọi API, dựng PWA để dùng trên điện thoại |
| Database | **MySQL 8.4** | Đủ sức cho quy mô doanh nghiệp vài nghìn người |
| Cache / Queue | **Redis 7** | Dùng cho queue, cache, session, rate limit |
| Lưu trữ ảnh | **Cloudflare R2** | S3-compatible, egress miễn phí |
| Xác thực | **Laravel Sanctum 4.3** | Cookie-based cho SPA, đủ cho ứng dụng nội bộ |
| Dữ liệu máy chủ (FE) | **TanStack Query 5** | Kèm Zod 4 và react-hook-form |

**Không dùng RabbitMQ.** Đây là monolith Laravel, Redis Queue xử lý đủ mọi nhu cầu. Nếu sau này thật sự cần, đổi driver trong `config/queue.php` là xong, không phải viết lại.

### Cấu hình gửi email

Mã OTP đăng nhập đi qua email, nên **email hỏng là cả công ty không đăng nhập được**. Đây là rủi ro lớn nhất của việc chọn email OTP thay vì ứng dụng xác thực.

**Hiện tại (dev):** mọi email vào **Mailpit** — container giả chạy local, không ra internet. Xem tại `localhost:8025`.

```
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS="khong-tra-loi@explus.vn"   # chỉ là nhãn hiển thị, hộp thư này không tồn tại
```

**Khi lên production** — chỉ sửa `.env`, không đụng mã nguồn. Ba lựa chọn:

| | Ưu | Nhược |
|---|---|---|
| **Google Workspace** | Nhanh nhất nếu công ty đã dùng. Cần bật 2FA cho tài khoản rồi tạo *App Password* — mật khẩu thường không dùng được | Giới hạn ~500 mail/ngày |
| **SendGrid / Amazon SES / Mailgun** | Sinh ra cho việc này: tỷ lệ vào hộp thư chính cao, có thống kê. SES rẻ nhất (~0.10 USD / 1000 mail) | Phải đăng ký và xác minh tên miền |
| **Mail server công ty** | Không phụ thuộc bên thứ ba | Tuỳ hạ tầng sẵn có |

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=no-reply@explus.vn
MAIL_PASSWORD=<app password>
MAIL_FROM_ADDRESS=no-reply@explus.vn
```

Và **bỏ service `mailpit` khỏi `docker-compose.yml`** — nó chỉ dành cho dev.

⚠️ **Bắt buộc cấu hình SPF và DKIM cho tên miền `explus.vn`.** Thiếu hai bản ghi DNS này thì mail chứa mã OTP rơi vào spam, và nhân viên không nhận được mã nghĩa là không đăng nhập được. Dùng địa chỉ gửi thật thuộc `explus.vn`, đừng dùng địa chỉ bịa.

Nếu email trở thành điểm yếu, đổi sang ứng dụng xác thực bằng một biến: `TWO_FACTOR_DRIVER=totp`.

#### Đổi kênh trên hệ thống đang chạy — một lỗi suýt khoá cả công ty

Lời khuyên trên **từng là một cái bẫy**, và nó nằm ngay trong tài liệu này.

`hasTwoFactorEnabled()` chỉ đọc cột `two_factor_confirmed_at`. Cột đó nói "người này đã xác nhận một lần nào đó", nhưng **không nói xác nhận bằng kênh nào**. Kênh email không lưu gì trên người dùng — nó sinh mã mới mỗi lần và tra bảng `two_factor_codes`, nên ai bật OTP qua email đều có `confirmed_at` mà `two_factor_secret` rỗng.

Đổi `TWO_FACTOR_DRIVER=totp` trên hệ thống đang chạy:

```
đăng nhập → confirmed_at có → đẩy sang màn nhập mã TOTP
          → TotpProvider::verify() thấy secret null → trả false
          → không mã nào đúng, với MỌI người, kể cả quản trị viên
```

Không còn ai vào được để sửa. Đường thoát duy nhất là chạy tay trong database.

**Đã sửa:** thêm `isEnrolled()` vào `TwoFactorProvider`. Mỗi kênh tự trả lời "người này đã thiết lập xong cho TÔI chưa" — email chỉ cần `confirmed_at`, TOTP cần thêm `secret`. `LoginController` hỏi provider thay vì đọc cột.

Kết quả: đổi driver thì người dùng kênh cũ được đưa đi **thiết lập lại**, quét mã QR một lần rồi vào bình thường. Không ai bị khoá. Ba test ở `DriverSwitchTest` khoá lại điều đó — test đầu tiên viết ra để **chứng minh lỗi có thật** trước khi sửa.

Bài học: `two_factor_confirmed_at` là một cột **thiếu thông tin**, không phải sai. Nó không mang tên kênh, nên mọi câu hỏi "người này dùng được không" phải hỏi kênh chứ không hỏi cột.


### Quên mật khẩu

```
POST /api/v1/auth/forgot-password   → luôn 200, luôn cùng một câu
POST /api/v1/auth/reset-password    → 204
```

Trước phần này, nhân viên quên mật khẩu phải nhắn admin — và admin phải đặt mật khẩu hộ người khác. `lang/vi/passwords.php` nằm trong dự án từ lâu mà không đường nào gọi tới.

#### Luôn trả về cùng một câu, dù email có tồn tại hay không

Điểm quan trọng nhất, và là chỗ hầu hết bản cài đặt làm sai. Trả "email không tồn tại" là **biến trang quên mật khẩu thành công cụ dò danh sách nhân sự**: gõ vào vài trăm địa chỉ và biết chính xác ai làm ở công ty này — với một hệ thống nội bộ thì đó là danh sách để nhắm lừa đảo.

Người đã nghỉ việc cũng nhận đúng câu đó, chỉ khác là **không email nào được gửi**. Nói "tài khoản đã bị khoá" là xác nhận người đó từng làm ở đây. Và cho họ đặt lại mật khẩu là mở một đường quay lại hệ thống sau khi đã bị thu hồi quyền.

Có test so **nội dung phản hồi từng byte** giữa email có thật và email bịa — chỉ một chữ khác nhau là đủ để dò.

`reset-password` gộp mọi lý do thất bại vào một câu (token sai, hết hạn, email không có). Tách ra thì đường này lại thành công cụ dò, và công sức ở đường bên cạnh thành vô nghĩa.

#### Ba việc phải làm cùng lúc khi mật khẩu đổi

1. **Đổi `remember_token`** — mọi thiết bị còn cookie "ghi nhớ đăng nhập" bị đá ra
2. **Xoá token Sanctum**
3. **Ghi nhật ký nhân sự** — `causer` để `null`, phân biệt được "tự làm" với "admin đặt hộ"

Bỏ sót (1) hoặc (2) thì người đặt lại mật khẩu vì nghi bị chiếm tài khoản sẽ thấy kẻ kia vẫn còn nguyên phiên.

**Xác thực hai lớp không bị đụng tới.** Đổi mật khẩu không phải lý do để hạ lớp bảo vệ thứ hai — và chính điều đó khiến một token đặt lại mật khẩu bị lộ vẫn chưa đủ để vào được hệ thống.

#### Email này không đi qua tuỳ chọn thông báo

`ResetPasswordNotification` cố ý **không** kế thừa `PreferenceAwareNotification`. Lớp kia đọc tuỳ chọn nhận thông báo — mà tuỳ chọn đó chỉ có nghĩa với người đang dùng hệ thống bình thường. Ở đây người ta không đăng nhập được: nếu họ từng tắt email thì họ sẽ **không bao giờ lấy lại được tài khoản**, và không hiểu vì sao. Đây là email hạ tầng, không phải thông báo nghiệp vụ.

Gửi qua hàng đợi `auth` — cùng hàng với mã OTP, đứng đầu ưu tiên của Horizon, vì người dùng đang đứng chờ trước màn hình.

#### Hạn mức

Hai lớp: **3 lần / 15 phút mỗi email** và **10 lần / 15 phút mỗi IP**. Chỉ chặn theo email thì máy quét đổi email mỗi lần là đi qua; chỉ chặn theo IP thì cả văn phòng dùng chung một IP sẽ chặn nhầm nhau. Laravel còn có `throttle` riêng của bộ đặt lại mật khẩu (60 giây giữa hai lần cho cùng một người) — lớp đó chặn dội thư rác, lớp ở đây chặn dò quét.

Mật khẩu mới vẫn qua `Password::defaults()`: tối thiểu 12 ký tự, có chữ và số, đối chiếu HaveIBeenPwned. Viết luật riêng ở đường này là biến nó thành cửa sau.

#### Dọn thông báo cũ

```bash
php artisan notifications:prune [--dry-run]
```

Chạy tự động **03:15 sáng Chủ nhật**. Đã đọc quá 90 ngày thì xoá; chưa đọc thì giữ tới 365 ngày — xoá một thông báo chưa ai đọc là xoá thứ có thể còn cần, người nghỉ dài về vẫn nên thấy mình bỏ lỡ gì.

Bảng `notifications` trước đó **không có gì dọn nó**. Riêng lời nhắc báo cáo đã là ~53.000 dòng một năm ở quy mô 200 người.

Xoá theo lô 1.000 dòng: `DELETE` một phát trên vài trăm nghìn dòng khoá bảng đủ lâu để mọi request đang chạy phải xếp hàng — mà bảng này bị chạm ở **mỗi lần tải trang** (cái đếm số thông báo chưa đọc). Có test 1.200 dòng để bắt lỗi "xoá đúng một lô rồi thoát" — lỗi đó không lộ ra với 5 dòng, nó chỉ lộ ở production dưới dạng bảng vẫn phình đều dù lệnh tuần nào cũng báo thành công.

**Không đụng tới nhật ký kiểm toán.** `user_activities` và `payroll_audits` phải sống lâu hơn mọi chính sách dọn dẹp; có test khoá lại điều đó.

---

### Sao lưu và diễn tập phục hồi

```bash
docker compose run --rm backup once     # sao lưu ngay
docker compose run --rm backup drill    # diễn tập phục hồi ngay
```

Tự động: sao lưu **02:15 mỗi ngày**, diễn tập phục hồi **03:45 Chủ nhật**.

#### Backup chưa từng phục hồi thử thì coi như chưa có

Đây là câu quan trọng nhất của cả mục này. Chuỗi hỏng thường gặp **không phải** "quên làm backup" mà là bốn thứ dưới đây, và cả bốn đều **im lặng** — lịch chạy xanh mỗi đêm, thư mục đầy file, mọi người yên tâm cho tới đúng ngày cần dùng:

| Kiểu hỏng | Ai bắt được |
|---|---|
| File dump rỗng hoặc cụt vì đĩa đầy giữa chừng | Chốt kiểm kích thước trong `backup.sh` |
| Mã hoá bằng khoá mà không ai còn giữ khoá giải | `restore-drill.sh` — nó thật sự giải mã |
| Dump thiếu bảng vì thiếu quyền | Diễn tập đếm số dòng từng bảng |
| Sai charset, tiếng Việt phục hồi thành dấu hỏi | Diễn tập đọc lại bảng `roles` |

Diễn tập phục hồi vào một **database riêng** rồi xoá đi. Phục hồi đè lên database thật thì bài diễn tập chính là sự cố.

Nó là **một dòng cron riêng**, không phải bước cuối của backup: gộp lại thì một bài diễn tập hỏng làm cả job backup báo lỗi, và phản xạ tự nhiên của người trực là tắt bớt phần gây ồn — thường là tắt đúng phần diễn tập.

#### Ba lỗi thật gặp khi dựng, đều là loại hỏng im lặng

**1. Client MariaDB không dump nổi MySQL 8.4.** Bản đầu dùng Alpine cho nhẹ; `apk add mysql-client` thực chất cài client **MariaDB 10.11**. Bản dump ra **20 byte**. Chốt kiểm kích thước bắt được và từ chối giữ file — nếu không nó đã lặng lẽ ghi đè bản tốt bằng một file rỗng. Đã đổi nền sang chính image `mysql:8.4`.

**2. `mysqldump | gzip` che mất lỗi của mysqldump.** Pipeline trả về mã thoát của **gzip**. Dump thất bại giữa chừng — mất kết nối, hết quyền, hết đĩa — mà gzip vẫn nén xong phần nhận được và thoát 0. Đã thêm `set -o pipefail`, và đó là lý do script dùng bash chứ không phải sh.

**3. CRLF.** Script viết trên Windows vào container thành `#!/bin/bash\r`, Linux báo **"bad interpreter: No such file or directory"** trỏ vào chính file vừa COPY — rất dễ đọc nhầm thành lỗi đường dẫn. Dính hai lần. Đã chặn ở hai tầng: `.gitattributes` khai `*.sh eol=lf`, và `sed -i 's/\r$//'` ngay trong Dockerfile để host viết kiểu gì cũng không còn quan trọng.

#### Mã hoá

`age` với khoá bất đối xứng: container sao lưu chỉ cần **khoá công khai**. Máy chủ bị chiếm thì kẻ tấn công vẫn không mở được các bản sao lưu cũ.

Bản backup là bản sao **đầy đủ** của lương từng người, số điện thoại, mọi bình luận — nằm ngoài mọi lớp phân quyền của ứng dụng, và thường được chép sang chỗ khác. Chỗ dễ lộ nhất lại đang giữ dữ liệu nhạy cảm nhất.

```bash
docker compose run --rm --entrypoint sh backup -c 'age-keygen' > storage/keys/backup-key.txt
# rồi đặt BACKUP_AGE_PUBLIC_KEY trong .env
```

⚠️ **Mất `storage/keys/backup-key.txt` là mất khả năng mở mọi bản sao lưu.** File này đã bị `.gitignore` chặn; cất một bản ở nơi khác máy chủ.

#### Tài khoản diễn tập có quyền riêng, hẹp

Lần chạy đầu tiên của bài diễn tập **thất bại** vì tài khoản ứng dụng không tạo được database. Đó là một thất bại tốt — nó chứng minh quyền của ứng dụng đang thật sự hẹp. Cấp riêng:

```sql
CREATE USER 'restore_drill'@'%' IDENTIFIED BY '...';
GRANT ALL PRIVILEGES ON `explus_restore_drill`.* TO 'restore_drill'@'%';
GRANT CREATE, DROP ON *.* TO 'restore_drill'@'%';
```

Tuyệt đối không dùng `root`: container này chạy không người trông và là chỗ duy nhất cầm khoá giải mã.

---

### Xoá dữ liệu cá nhân người đã nghỉ việc

```bash
php artisan users:anonymise --dry-run     # xem ai tới hạn
php artisan users:anonymise               # làm thật, có hỏi xác nhận
php artisan users:anonymise --user=<uuid> # theo yêu cầu của chính người đó
```

#### Ẩn danh, không xoá

Nghị định 13/2023/NĐ-CP cho chủ thể dữ liệu quyền yêu cầu xoá dữ liệu cá nhân. Nhưng xoá thẳng dòng `users` là **phá huỷ dữ liệu của công ty**, không phải bảo vệ dữ liệu của cá nhân: task mất người thực hiện, báo cáo mất tác giả, nhật ký kiểm toán mất người thực hiện hành vi — tức là nhật ký hết giá trị.

Ẩn danh giải quyết đúng phần cần giải quyết: **thông tin nhận dạng biến mất, dấu vết công việc ở lại** dưới một cái tên vô danh.

| Xoá đi | Giữ lại |
|---|---|
| Tên, email, số điện thoại, mã nhân viên | Task, bình luận, báo cáo đã viết |
| Mật khẩu, token, thiết lập 2FA | Bảng công, bảng lương các kỳ đã chốt |
| Thông báo trong ứng dụng | **Nhật ký kiểm toán** |

Email mới dùng tên miền `.invalid` — RFC 2606 dành riêng nó để bảo đảm không bao giờ phân giải được, nên thư gửi nhầm tới đó không thể tới tay ai.

#### Không chạy tự động theo lịch, có chủ ý

Thao tác **không đảo ngược được**, và chạy nhầm không có đường sửa. Một lịch chạy nền âm thầm xoá dữ liệu cá nhân mỗi đêm là loại tự động hoá mà hậu quả chỉ lộ ra khi đã quá muộn — ví dụ khi ai đó đặt sai `terminated_at`, hoặc khi một nhân viên nghỉ rồi quay lại làm.

#### Một cái bẫy đã mắc khi viết

`password_reset_tokens` đánh chỉ mục theo **email**, không theo `user_id`. Bản đầu xoá token *sau* khi đã ghi đè email, nên câu lệnh chạy với địa chỉ mới và không khớp dòng nào — một token còn hiệu lực của địa chỉ cũ vẫn nằm đó, im lặng. Có test khoá lại.

#### Lý do nghỉ được xoá, nội dung task thì không

Hai thứ trông giống nhau — đều là chữ do người dùng gõ — nhưng thuộc hai loại khác hẳn.

Nội dung task, bình luận, báo cáo ngày là **tài sản công việc của công ty**; người viết chỉ là tác giả. Xoá chúng là phá dữ liệu của công ty.

Lý do xin nghỉ thì do chính người đó viết về **hoàn cảnh riêng**, và rất thường là thông tin sức khoẻ: *"nghỉ ốm"*, *"đi khám"*, *"về quê chăm mẹ"*. Nghị định 13 xếp dữ liệu sức khoẻ vào nhóm **dữ liệu cá nhân nhạy cảm** — mức bảo vệ cao hơn dữ liệu thường.

Nên nó bị xoá, nhưng **chỉ phần chữ**: `reason` và `review_note`. Dòng đơn nghỉ vẫn còn nguyên ngày, loại và người duyệt — đó mới là thứ làm nên chứng từ lao động, và nó không cần đến câu chữ tự do.

##### Xoá qua event, vì Identity không được gọi sang Leave

`AnonymiseUserAction` nằm ở miền Identity, mà quy tắc phụ thuộc cấm Identity gọi thẳng sang miền khác. Đường đúng — và README đã ghi sẵn từ đầu — là **bắn event**:

```
AnonymiseUserAction  ──event UserAnonymised──▶  ScrubLeaveReasons (miền Leave)
```

Listener chạy **đồng bộ, trong cùng giao dịch**, cố ý không đưa vào hàng đợi: đây là thao tác tuân thủ pháp luật. Một listener chạy nền mà thất bại sẽ để dữ liệu nhạy cảm nằm nguyên trong khi nhật ký đã ghi "đã xoá", và không ai biết cho tới khi có người đi kiểm.

Đây cũng là chỗ mở sẵn cho các miền sau: miền nào giữ dữ liệu cá nhân thì tự đăng ký nghe event và dọn phần của mình.

#### Giới hạn phải nói ra, không giấu

**Bản sao lưu cũ vẫn chứa dữ liệu gốc** cho tới khi hết hạn lưu (mặc định 30 ngày). Điều này phải nằm trong chính sách gửi cho nhân viên, không phải để phát hiện sau.

`IDENTITY_RETENTION_MONTHS` mặc định 60 tháng chỉ là **mốc để bàn**, chọn theo thời hạn lưu chứng từ kế toán. Con số cuối cùng là quyết định của công ty và phải có người ký.

---

### Kiểm tra tình trạng hạ tầng

```
GET /api/v1/health          → 200 {"status":"ok"|"degraded", ...}
                            → 503 {"status":"down", ...}
```

```json
{
  "status": "ok",
  "components": [
    { "name": "database", "status": "ok",      "duration_ms": 4 },
    { "name": "cache",    "status": "ok",      "duration_ms": 0 },
    { "name": "storage",  "status": "skipped", "duration_ms": 0 }
  ]
}
```

#### Vì sao không dùng `/up` có sẵn của Laravel

`/up` chỉ trả lời "tiến trình PHP còn sống". Nó **vẫn xanh khi database đã sập**: ứng dụng khởi động được, route trả 200, và bộ giám sát báo mọi thứ ổn trong lúc không ai đăng nhập nổi.

Hai đường tồn tại song song có chủ ý: `/up` là *liveness* (có nên khởi động lại tiến trình không), `/api/v1/health` là *readiness* (có nên gửi người dùng vào máy chủ này không).

#### Ba mức, không phải hai

| Mức | Khi nào | Mã HTTP |
|---|---|---|
| `ok` | Mọi thứ chạy | 200 |
| `degraded` | Kho tệp hỏng — vẫn giao việc, chấm công, báo cáo được | 200 |
| `down` | Database hoặc cache chết | **503** |

Rút cả máy chủ ra khỏi vòng phục vụ chỉ vì không mở được ảnh là **đổi một sự cố nhỏ lấy một sự cố lớn**. `storage` trả `skipped` khi chưa bật R2 — báo đỏ một thành phần chưa dùng tới là cách nhanh nhất để người trực ban quen với màu đỏ rồi thôi nhìn nó.

#### Mỗi phép kiểm phải thật sự chạm tới thành phần đó

`DB::connection()` **không mở kết nối** — Laravel kết nối lười. Kiểm bằng cách gọi hàm đó là tự lừa mình: nó xanh kể cả khi MySQL đã tắt. Phải chạy một câu lệnh thật.

Cache cũng vậy: phải **ghi rồi đọc lại và so**. Redis còn sống nhưng hết bộ nhớ sẽ nhận lệnh ghi rồi lặng lẽ vứt đi, và một phép kiểm chỉ gọi `put()` sẽ báo xanh suốt trong tình huống đó. Có test khoá lại.

Phép kiểm kho tệp chỉ **liệt kê**, không ghi: nó chạy 30 giây một lần, mãi mãi — ghi một tệp mỗi lần là sau một năm có một triệu tệp rác phải trả tiền lưu trữ.

#### Timeout, và một giới hạn không vá được

Đo thật bằng cách tắt MySQL trong container:

| Tình huống | Thời gian phát hiện |
|---|---|
| Database từ chối kết nối | 0,16s |
| Địa chỉ không định tuyến được | 2,00s — đúng bằng `PDO::ATTR_TIMEOUT` |
| **Tên máy không phân giải được** | **~17s — timeout KHÔNG bó được** |

Dòng cuối không vá được ở tầng PHP: `getaddrinfo()` chặn ở tầng hệ điều hành, `PDO::ATTR_TIMEOUT` chỉ tính từ lúc đã có địa chỉ IP. Nó chỉ xảy ra khi bản ghi DNS biến mất — đúng cảnh `docker compose stop mysql`. Ở production database nằm ở máy có tên phân giải ổn định, nên hai dòng đầu mới là tình huống thật.

Timeout dùng **kết nối riêng**, không sửa kết nối chung: hai giây là quá ngắn cho một truy vấn báo cáo nặng. Kết nối đóng ngay sau khi kiểm, vì MySQL có trần `max_connections`.

#### Không đăng nhập, và không để lọt gì

Bộ giám sát không có tài khoản, và một phép kiểm chỉ chạy được khi đăng nhập được thì vô dụng đúng lúc database sập. Đổi lại, phản hồi **không chứa thông điệp lỗi, tên máy chủ, phiên bản, hay tên sản phẩm** — tên thành phần cố ý chung chung (`database`, `cache`, `storage`, không phải `mysql`, `redis`, `cloudflare-r2`). Lỗi gốc đi vào log.

Có một test đọc thẳng chuỗi JSON thô rồi khẳng định không chứa host, tên database, tên tài khoản, hay chữ `SQLSTATE`. Cách hỏng tự nhiên nhất của một endpoint như thế này là ai đó thêm `'error' => $e->getMessage()` cho dễ gỡ lỗi.

Hạn mức riêng `throttle:120,1` thay cho `throttle:api`: bộ giám sát gọi đều đặn từ một địa chỉ IP, dùng chung bộ đếm với người dùng thật thì hoặc nó ăn hết hạn mức của người ta, hoặc chính nó bị 429 rồi báo động giả.

---

### Đường dẫn tệp đính kèm và Cloudflare R2

#### Ổ đĩa

R2 không hỗ trợ ACL. Trong `config/filesystems.php`:

```php
'r2' => [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => 'auto',
    'bucket' => env('R2_BUCKET'),
    'url' => env('R2_PUBLIC_URL'),  // để trống = bucket riêng tư
    'endpoint' => env('R2_ENDPOINT'),
    'use_path_style_endpoint' => true,
    'visibility' => null,          // R2 không hỗ trợ ACL — bắt buộc để null
    'throw' => true,
],
```

#### Token phải là loại nào

Tạo ở **dash.cloudflare.com → R2 → API → Create Account API token**.

**Không dùng User API token.** Loại đó gắn với tài khoản cá nhân và *chết theo người tạo*: hôm người đó nghỉ việc, backend mất quyền vào toàn bộ ảnh của hệ thống, và không ai biết cho tới khi ảnh ngừng hiện. Account API token sống độc lập với nhân sự.

Quyền phải là **Object Read & Write**, giới hạn vào đúng một bucket. Chỉ có Read thì kết nối thành công, `headBucket` và `listObjectsV2` đều xanh — nhưng mọi lần tải ảnh lên đều `AccessDenied`. Đây là lỗi im lặng đúng nghĩa: cấu hình trông như đã xong.

`R2_ENDPOINT` có dạng `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`, **không kèm tên bucket**.

#### Hai kiểu URL, chọn theo cấu hình

`App\Support\Media\MediaUrl` quyết định, và quyết định theo **ổ đĩa ghi trên chính bản ghi media** chứ không theo `MEDIA_DISK`:

| Ổ đĩa có `url`? | Đường dẫn phát ra | Vì sao |
| --- | --- | --- |
| Có (`public` khi dev, hoặc R2 đã gắn custom domain) | `getUrl()` — ổn định | Tệp đọc công khai được, trình duyệt cache lại |
| Không (bucket R2 riêng tư — mặc định) | `getTemporaryUrl()` — ký, hạn 30 phút | `getUrl()` ghép ra `{endpoint}/{bucket}/{path}`, địa chỉ đó luôn trả 403 |

Đọc theo bản ghi vì hai lý do: tệp tải lên hồi còn `MEDIA_DISK=public` vẫn nằm ở đĩa cũ sau khi chuyển sang R2 và vẫn phải xem được; và bản gốc với bản thu nhỏ được phép nằm ở hai ổ khác nhau (`MEDIA_CONVERSIONS_DISK`).

**Đánh đổi đã biết của đường dẫn ký.** Ai cầm được đường dẫn là xem được tệp, không cần đăng nhập, cho tới khi hết hạn — chuyển tiếp một link ảnh báo cáo ra ngoài công ty là nó dùng được. Vì vậy hạn để 30 phút (`MEDIA_TEMPORARY_URL_DEFAULT_LIFETIME`) chứ không phải vài ngày. Chặn hẳn thì phải cho ảnh đi qua một endpoint của mình và kiểm tra Policy từng lần tải; việc đó còn để ngỏ ở [mục 1.10](#110-vận-hành--đưa-vào-sử-dụng).

Đánh đổi thứ hai: chữ ký gắn mốc thời gian nên **đường dẫn đổi sau mỗi lần gọi API**, trình duyệt tải lại ảnh mỗi lần danh sách bình luận làm mới. Chấp nhận được với ảnh nội bộ cỡ nhỏ. Nếu thành vấn đề thì lời giải là endpoint chuyển tiếp nói trên, **không phải kéo dài hạn**.

#### Kiểm chứng

5 test ở `tests/Feature/Media/MediaUrlTest.php`. Ký một đường dẫn S3 là phép tính thuần cục bộ (HMAC trên chuỗi yêu cầu), không gọi mạng — nên bộ test ép ổ `r2` bằng khoá giả và vẫn kiểm được chữ ký thật, chạy được trong CI mà không cần bucket.

Một test khoá lại đúng cái bẫy: trên ổ riêng tư, `getUrl()` vẫn trả về một chuỗi trông như URL, không ném lỗi, không có gì đỏ — chỉ là mở lên thì 403.

---

## Kiến trúc & quy ước mã nguồn

> **Chương này là bắt buộc.** Mọi pull request không tuân thủ sẽ bị CI chặn — xem mục [Kiểm soát tự động](#kiểm-soát-tự-động).

### Nguyên tắc nền: Modular Monolith

Một ứng dụng Laravel duy nhất, nhưng chia theo **miền nghiệp vụ (domain)**, không chia theo loại file. Lý do rất cụ thể: 5 đợt trong lộ trình chính là 5 miền tương đối độc lập — Task, Report, Attendance, Leave, Payroll. Chia theo domain thì mỗi đợt thêm một thư mục; chia theo loại file (cách mặc định của Laravel) thì đến đợt 3 thư mục `app/Models` sẽ có 40 file không liên quan gì nhau.

Đây không phải microservice. Vẫn một codebase, một database, một lần deploy. Chỉ là ranh giới bên trong rõ ràng, để sau này nếu thật sự cần tách thì có đường mà tách.

### Quy tắc phụ thuộc — điều quan trọng nhất trong tài liệu này

```
   Http  ─────────►  Domain  ─────────►  Support
(controller,        (nghiệp vụ         (tiện ích
 request,            thuần)             dùng chung)
 resource)

                    Domain A  ╳  Domain B
                       └── chỉ qua Event ──┘
```

| Quy tắc | Cụ thể |
|---|---|
| **Domain không biết tới Http** | Trong `app/Domain/` cấm xuất hiện `Request`, `Response`, `Auth::user()`, `session()`, `redirect()`. Cần biết ai đang thao tác thì **truyền vào** như tham số, đừng đi lấy. |
| **Domain không gọi thẳng Domain khác** | `Task` cần báo cho `Notification` thì bắn event `TaskAssigned`, không gọi `NotificationService`. Đây là thứ giữ cho monolith không thành mớ bòng bong. |
| **Ngoại lệ duy nhất: `Identity` là shared kernel** | Mọi miền đều phải tham chiếu người dùng và phòng ban (`assignee_id`, `manager_id`, `department_id`). Nên `Identity` được phép bị phụ thuộc bởi tất cả, và bản thân nó không phụ thuộc miền nào. Ràng buộc này nằm trong `deptrac.yaml` và architecture test. |
| **Http không chứa nghiệp vụ** | Controller chỉ làm 3 việc: nhận request đã validate, gọi một Action, trả về Resource. Thấy `if` nghiệp vụ trong controller là sai chỗ. |
| **Model không chứa nghiệp vụ** | Model chỉ khai báo quan hệ, cast, scope truy vấn. Không đặt `$task->assignTo($user)` với 40 dòng logic bên trong. |
| **Support không biết Domain** | Tầng dưới cùng, thuần kỹ thuật, không nghiệp vụ. |

### Cấu trúc thư mục backend

```
app/
├── Domain/                        # Nghiệp vụ thuần — không biết HTTP tồn tại
│   ├── Task/
│   │   ├── Actions/               # CreateTaskAction, ChangeTaskStatusAction, AssignTaskAction
│   │   ├── Data/                  # DTO vào/ra và bộ giá trị dùng chung: CreateTaskData, AttachmentRules
│   │   ├── Enums/                 # TaskStatus, TaskPriority
│   │   ├── Events/                # TaskAssigned, TaskCompleted, TaskOverdue
│   │   ├── Exceptions/            # InvalidStatusTransitionException
│   │   ├── Jobs/                  # NotifyUpcomingDeadlinesJob
│   │   ├── Listeners/
│   │   ├── Models/                # Task, TaskComment, TaskActivity
│   │   ├── Observers/             # Ghi nhật ký kiểm toán
│   │   ├── Policies/              # TaskPolicy
│   │   ├── Queries/               # Query object cho lọc/tìm kiếm phức tạp
│   │   ├── States/                # State machine trạng thái task
│   │   └── TaskServiceProvider.php
│   ├── Identity/                  # Người dùng, phòng ban, vai trò, quyền
│   ├── Report/                    # Đợt 2
│   ├── Attendance/                # Đợt 3
│   ├── Leave/                     # Đợt 4
│   └── Payroll/                   # Đợt 4–5
│
├── Http/                          # Chỉ điều phối — không chứa nghiệp vụ
│   ├── Controllers/Api/V1/
│   ├── Middleware/
│   ├── Requests/                  # Validate tại biên HTTP
│   └── Resources/                 # Serialize đầu ra (JSON:API)
│
├── Support/                       # Dùng chung, thuần kỹ thuật
│   ├── Concerns/
│   └── Contracts/
│
└── Providers/
```

Mỗi domain có `ServiceProvider` riêng để đăng ký binding, event listener, policy của chính nó. Thêm domain mới = thêm một thư mục và một dòng trong `bootstrap/providers.php`.

### Controller phải mỏng đến mức nào

Đây là chuẩn, không phải gợi ý:

```php
final class TaskController
{
    #[Authorize('create', Task::class)]
    public function store(StoreTaskRequest $request, CreateTaskAction $action): TaskResource
    {
        $task = $action->execute(CreateTaskData::from($request));

        return TaskResource::make($task);
    }
}
```

Ba dòng. Validate ở `StoreTaskRequest`, phân quyền ở `TaskPolicy` qua attribute `#[Authorize]` của Laravel 13, nghiệp vụ ở `CreateTaskAction`, định dạng đầu ra ở `TaskResource`. Controller không biết gì về nghiệp vụ.

### Các pattern sử dụng

| Pattern | Dùng ở đâu | Công cụ |
|---|---|---|
| **Action** (lớp một nhiệm vụ) | Mọi thao tác ghi nghiệp vụ | Class thuần, một phương thức `execute()` |
| **DTO** | Truyền dữ liệu qua các tầng | `spatie/laravel-data` |
| **State Machine** | Trạng thái task, duyệt đơn nghỉ, duyệt điều chỉnh công | `spatie/laravel-model-states` |
| **Policy** | Phân quyền theo từng bản ghi | Laravel native + `#[Authorize]` |
| **Form Request** | Validate tại biên HTTP | Laravel native |
| **API Resource** | Serialize đầu ra | JSON:API resources (mới ở Laravel 13) |
| **Query Object** | Lọc, sắp xếp, tìm kiếm danh sách | `spatie/laravel-query-builder` |
| **Event / Listener** | Giao tiếp **giữa** các domain | Laravel native |
| **Observer** | Ghi nhật ký kiểm toán tự động | Laravel native + `spatie/laravel-activitylog` |
| **Repository** | **Chỉ** cho nguồn dữ liệu bên ngoài | Interface + implementation |
| **Service Provider** | Đăng ký binding cho từng domain | Laravel native |

**Vì sao chọn Action thay vì Service:** một `TaskService` sau 6 tháng sẽ có 30 phương thức và 800 dòng, không ai dám sửa. Một `ChangeTaskStatusAction` thì luôn đọc được trong một màn hình, test được độc lập, và tên class nói đúng nó làm gì.

### Pattern cố tình KHÔNG dùng

Phần này quan trọng ngang phần trên. "Chuẩn" không có nghĩa là dùng càng nhiều pattern càng tốt — pattern đặt sai chỗ làm code khó đọc hơn là không có pattern.

**Repository bọc quanh Eloquent.** Eloquent đã là Active Record kèm Query Builder sẵn. Viết thêm `TaskRepository::find()` chỉ để gọi `Task::find()` là nghi thức rỗng: không test nhanh hơn (vẫn cần database), không đổi ORM được (không ai đổi ORM giữa dự án).
*Chỗ interface thật sự chính đáng* là ranh giới ra **dịch vụ bên ngoài** — gửi thông báo qua Zalo OA hay Telegram (đợt 2), lưu tệp lên R2. Ở đó có nhiều nhà cung cấp thật, có khả năng đổi thật, và cần giả lập khi test. Dữ liệu trong database của chính hệ thống thì không.

**CQRS / Event Sourcing.** Quá nặng cho bài toán này. Yêu cầu truy vết lịch sử đã được giải quyết bằng Observer + `activitylog`, đơn giản hơn nhiều lần.

**Hexagonal/Clean Architecture đầy đủ.** Chỉ tạo interface khi có **ít nhất hai implementation thật**, hoặc có ranh giới hệ thống ngoài. Tạo interface cho mọi class chỉ để "đúng sách" sẽ nhân đôi số file mà không ai được lợi.

**Generic Base Controller / Base Service.** Kế thừa để tái sử dụng nghe hay lúc đầu, tới class thứ 5 thì base class đầy `if` xử lý ngoại lệ của từng con. Ưu tiên composition.

### Quy ước đặt tên

| Loại | Quy ước | Ví dụ |
|---|---|---|
| Action | Động từ + danh từ + `Action` | `CreateTaskAction`, `ApproveLeaveRequestAction` |
| DTO đầu vào | Danh từ + `Data` | `CreateTaskData`, `TaskFilterData` |
| Đối tượng kết quả | Danh từ + `Result` | `ImportUsersResult` |
| Event | Danh từ + động từ quá khứ | `TaskAssigned`, `LeaveRequestApproved` |
| Job | Động từ + danh từ + `Job` | `NotifyUpcomingDeadlinesJob` |
| Exception | Mô tả lỗi + `Exception` | `InvalidStatusTransitionException` |
| Bảng CSDL | snake_case, số nhiều, tiếng Anh | `task_comments`, `leave_requests` |
| Cột | snake_case, tiếng Anh | `due_date`, `payable_hours` |
| Enum | Số ít, giá trị dạng chuỗi | `TaskStatus::InProgress` → `'in_progress'` |
| API | kebab-case, danh từ số nhiều | `/api/v1/leave-requests` |

Mã nguồn và cơ sở dữ liệu dùng **tiếng Anh**. Chỉ giao diện người dùng và tài liệu dùng tiếng Việt. Trộn hai ngôn ngữ trong tên bảng/cột là nguồn cơn của rất nhiều lỗi về sau.

Mọi class đều khai báo `final` trừ khi có lý do rõ ràng để cho kế thừa. Mọi file đều có `declare(strict_types=1);`.

### Kiểm soát tự động

Quy ước không được máy kiểm tra thì sau 3 tháng sẽ không còn ai theo. Toàn bộ mục này chạy trong CI, sai là chặn merge:

- [x] **Laravel Pint** — định dạng code, chuẩn `laravel` preset
- [x] **Larastan mức 8** — phân tích tĩnh, không cho `mixed` trôi nổi
- [x] **Deptrac** — kiểm tra ranh giới tầng và ranh giới giữa các domain
- [x] **Pest + Architecture Test** — biến quy tắc kiến trúc thành test chạy được

Phần Pest arch test là thứ khiến chương này có hiệu lực thật:

```php
arch('Domain không biết tới tầng Http')
    ->expect('App\Domain')
    ->not->toUse([
        'Illuminate\Http\Request',
        'Illuminate\Http\Response',
        'Illuminate\Support\Facades\Auth',
        'Illuminate\Support\Facades\Session',
    ]);

arch('Các miền nghiệp vụ không gọi thẳng vào nhau')
    ->expect('App\Domain\Task')
    ->not->toUse('App\Domain\Attendance');

arch('Controller không được truy vấn trực tiếp')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('Không sót hàm debug')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

arch('Toàn bộ mã nguồn dùng strict types')
    ->expect('App')
    ->toUseStrictTypes();
```

### Cấu trúc frontend (Next.js)

Chia theo **tính năng**, không chia theo loại file — cùng triết lý với backend:

```
frontend/src/
├── app/
│   ├── (app)/                   # Nhóm route đã đăng nhập — dùng chung khung
│   │   ├── layout.tsx           #   sidebar + đầu trang
│   │   ├── page.tsx             #   Hôm nay của tôi
│   │   ├── tasks/ board/ team/ projects/
│   ├── login/                   # Nằm NGOÀI nhóm (app): toàn màn hình, không sidebar
│   └── manifest.ts              # Manifest PWA
├── features/
│   ├── tasks/
│   │   ├── api/                 # Hàm gọi API + TanStack Query hooks
│   │   ├── components/          # Component riêng của tính năng task
│   │   ├── hooks/
│   │   ├── schemas/             # Zod schema, khớp với validate backend
│   │   └── types/
│   ├── projects/ auth/ users/
│   └── notifications/
├── components/
│   ├── ui/                      # Component dùng chung, không biết nghiệp vụ
│   └── app-shell/               # Khung ứng dụng: sidebar, điều hướng
├── lib/                         # HTTP client, định dạng, tiện ích
└── types/api.ts                 # Sinh tự động từ OpenAPI của backend
```

| Quy tắc | Lý do |
|---|---|
| Dữ liệu máy chủ dùng **TanStack Query**, không nhét vào global store | Cache, retry, invalidate đã có sẵn; tự làm bằng Redux là viết lại từ đầu |
| Kiểu dữ liệu API **sinh tự động** từ OpenAPI, không gõ tay | Backend đổi field mà frontend không biết là lỗi lúc chạy; sinh tự động thì lỗi lúc build |
| Component trong `components/ui/` **không được** gọi API, cũng **không được** import `features/*` | Giữ chúng thuần để tái sử dụng và test. Luật `no-restricted-imports` trong `eslint.config.mjs` chặn thật, không chỉ là quy ước. Nhãn mang nghiệp vụ (trạng thái task, trạng thái dự án) thuộc về `features/`, không thuộc bộ UI dùng chung |
| Validate form bằng **Zod**, khớp Form Request phía Laravel | Người dùng biết lỗi trước khi gửi; server vẫn validate lại |
| TypeScript **strict**, cấm `any` | |
| Đổi giờ sang `Asia/Ho_Chi_Minh` ở tầng hiển thị, ghi cứng múi giờ chứ không lấy theo máy | Nhân viên mở máy đang để múi giờ khác vẫn phải thấy đúng hạn theo giờ công ty |
| Không dùng `useEffect` để đồng bộ state theo prop | Effect chạy sau khi đã vẽ xong nên để lại một khung hình hiện giá trị cũ. Luật `react-hooks/set-state-in-effect` chặn ở CI |

### Sắc theo miền — màu là chỉ dẫn đường, không phải trang trí

Mỗi khu vực của ứng dụng mang một sắc riêng.

| Khu vực | Sắc | | Khu vực | Sắc |
|---|---|---|---|---|
| Tổng quan | graphite | | Lương | hổ phách |
| Hôm nay của tôi | lime (thương hiệu) | | Thưởng dự án | hồng |
| Công việc · Kanban · Đội · Dự án | chàm | | Nhân sự | lam |
| Báo cáo ngày | tím | | Chấm công | lục lam |

#### Vấn đề nó giải quyết

Bản trước dùng **đúng một màu nhấn cho cả mười một mục điều hướng**. Hệ quả: Chấm công, Lương, Thưởng và Báo cáo nhìn y hệt nhau, nên người dùng phải *đọc* mới biết mình đang ở đâu. Có sắc riêng thì sau vài ngày họ nhận ra bằng màu và thôi đọc.

Đó là khác biệt giữa "nhiều màu cho vui" và "nhiều màu để dùng" — và là lý do bản này vừa rực rỡ hơn hẳn vừa **đỡ mỏi mắt hơn** khi dùng cả ngày.

Bốn mục họ công việc **dùng chung một sắc**, có chủ ý: chúng là một họ nghiệp vụ, tách sắc ra sẽ nói sai rằng chúng không liên quan nhau.

#### Lime vẫn là màu thương hiệu, không bị đụng tới

Logo, nút hành động chính, màn đăng nhập giữ nguyên lime. Trộn hai vai trò lại thì nút "Lưu" đổi màu theo trang đang mở, và người dùng mất luôn mốc nhận ra đâu là nút chính.

#### Lớp trung gian `tone` là mấu chốt

```
[data-tone="time"]  →  --tone, --tone-ink, --tone-surface, --tone-line
```

Trang khai `data-tone` **một lần** ở thẻ ngoài cùng; mọi thành phần bên trong chỉ đọc `--tone-*` mà không cần biết mình đang ở miền nào. Không có lớp này thì mỗi thành phần phải nhận một prop màu, và chỉ cần một chỗ quên truyền là lệch cả trang.

#### Mỗi sắc phải có biến `-ink` riêng cho chữ

Không dùng màu nền đặc làm màu chữ. Đây chính là lỗi đã mắc với lime ở bản trước: `#a9d900` trên nền trắng chỉ đạt **1,8:1**.

**Đã đo cả 24 tổ hợp bằng công thức WCAG, không ước lượng:**

| | Thấp nhất | Cao nhất | Kết quả |
|---|---|---|---|
| Nền sáng — chữ trên thẻ trắng | 5,02 (hổ phách) | 14,63 (graphite) | **24/24 đạt** |
| Nền sáng — chữ trên nền nhạt cùng sắc | 4,83 (lime) | 13,35 (graphite) | |
| Nền tối | 9,56 (chàm) | 15,02 (lime) | |

Ngưỡng AA là 4,5. Chỗ sát nhất là hổ phách trên nền vàng nhạt — vẫn dư.

#### Một lỗi hiệu ứng làm hỏng kéo thả

Bản đầu của hiệu ứng mở trang dùng `animation-fill-mode: both`. Nghe vô hại, và nó **làm bảng Kanban không kéo thả được**.

`both` = `backwards` + `forwards`, mà `forwards` giữ khung hình cuối **có hiệu lực vĩnh viễn**. Khung cuối khai `transform`, nên phần tử mãi mãi được coi là có transform — và **một tổ tiên có transform trở thành khối chứa của mọi con `position: fixed`**.

`DragOverlay` của dnd-kit là `position: fixed`, nằm bên trong `<TaskBoard>`, mà `<TaskBoard>` là con trực tiếp của `.enter`. Thẻ đang kéo bị định vị theo cái div đó thay vì theo màn hình, nên nó bay lệch khỏi con trỏ. **Không có lỗi nào trong console** — CSS hợp lệ, JavaScript hợp lệ, chỉ là kết quả sai.

Sửa bằng `backwards`: vẫn giữ đúng phần cần giữ (khung hình *đầu* trong lúc chờ hết độ trễ, để khối không loé lên trước lượt của nó) rồi buông hẳn sau khi chạy xong.

**Luật rút ra: hiệu ứng dùng chung cho cả ứng dụng thì không được để lại `transform` sau khi kết thúc.** Trang nào cũng có thể có menu, hộp thoại, hay thứ gì đó `position: fixed` bên trong. Đã rà và sửa cả `.rise-in` với `.stagger` ở màn đăng nhập tuy chúng chưa gây lỗi — cùng mầm bệnh.

#### Sensor của dnd-kit phải đi qua `useSensor`

Lỗi có sẵn từ trước, phát hiện khi tìm nguyên nhân ở trên. Bảng Kanban dựng tay mảng `{ sensor, options }` — đúng hình dạng nên TypeScript không kêu. Nhưng đọc thẳng mã nguồn `@dnd-kit/core` thì `useSensor` và `useSensors` **chỉ là `useMemo`**: chúng tồn tại đúng để ghi nhớ tham chiếu.

Không ghi nhớ thì mảng mang danh tính mới ở mỗi lần render, `DndContext` tính lại toàn bộ danh sách activator và sinh listener mới cho từng thẻ — ngay giữa lúc kéo, vì `setDangKeo` làm component render lại. Đúng loại lỗi hỏng khi bảng có nhiều thẻ chứ không hỏng lúc thử với ba thẻ.

#### Ba chi tiết làm nên cảm giác "2026"

**Bóng đổ ám sắc.** `color-mix` cho bóng mang màu của chính miền thay vì xám. Bóng xám dưới một thẻ có màu trông như thẻ bị bẩn; bóng cùng họ màu làm thẻ như đang phát ra ánh sáng của chính nó. Không ai gọi tên được khi nhìn, nhưng đây là thứ tách bản này khỏi bản trước rõ nhất.

**Vạch sắc ở hai đầu màn hình.** Mục đang mở ở thanh bên có vạch dọc bên trái; đầu trang có vạch ngang cùng màu, cùng độ dày. Mắt tự nối hai thứ đó lại.

**Con số được viết to.** Trang Tổng quan trước đây để số liệu ở cỡ 0,85rem — bằng cỡ chữ chú thích, nên nó đọc như một đoạn văn chứ không như bảng điều khiển. Giờ 2,6rem, nét đậm, chữ bó sát, `tabular-nums` để cột số không nhảy khi dữ liệu đổi.

Cộng thêm hiệu ứng mở trang: các khối hiện lên lần lượt cách nhau 45ms. Đủ để mắt lần theo thứ tự đọc, chưa đủ để ai phải chờ — và tắt hẳn khi người dùng bật `prefers-reduced-motion`.

---

### Nhận diện & hệ giao diện

Motif dấu **+** chính là chữ "plus" trong tên Explus, dùng xuyên suốt từ logo tới lưới nền. Màu nhấn là **lime điện** — đọc như tín hiệu "đi tiếp", tránh xanh SaaS và gradient tím đã quá quen.

| Hạng mục | Lựa chọn | Lý do |
|---|---|---|
| Chữ chính | **Be Vietnam Pro** | Bộ chữ hình học do người Việt thiết kế, có subset `vietnamese` nên dựng dấu chuẩn. Font thiếu subset này sẽ ghép dấu tạm và lệch — lỗi rất dễ lọt vì máy dev thường có sẵn font hệ thống che mất |
| Chữ kỹ thuật | **JetBrains Mono** | Mã OTP, mã khôi phục, nhãn bước — chữ đều bề rộng dễ đối chiếu từng ký tự |
| Token | CSS variable, ánh xạ qua `@theme inline` | Sáng/tối dùng chung một bộ tên, không nhân đôi class |

**Trang đăng nhập luôn ở chế độ tối**, bất kể máy đang để sáng hay tối (lớp `.stage-dark` ghi đè token). Đây là lựa chọn có chủ ý: đó là khoảnh khắc thương hiệu duy nhất trước khi vào app, và nền tối cho toàn quyền kiểm soát ánh sáng và chiều sâu. Phần app bên trong vẫn theo cài đặt của máy.

Nền dựng ba lớp chồng nhau:

1. **Aurora** — ba quầng gradient lớn trôi rất chậm (26 giây một chu kỳ), đủ để trang có cảm giác sống mà không gây chú ý
2. **Lưới dấu cộng** — `mask-image` nên đổi màu theo chế độ mà không cần hai ảnh; mờ dần ra rìa để không cắt ngang bố cục
3. **Hạt nhiễu** — SVG `feTurbulence` phủ `mix-blend-overlay`, xoá hiện tượng vệt màu của gradient và tạo chất liệu

Form nằm trên **thẻ kính** (`backdrop-filter` + viền gradient 1px sáng ở đỉnh mờ dần xuống đáy — mô phỏng ánh sáng rọi từ trên, cho cảm giác vật thể có khối).

Toàn bộ chuyển động tắt khi người dùng bật `prefers-reduced-motion`.

#### Làm lại phần đã đăng nhập (nền trung tính, có chiều sâu)

Bản đầu của phần trong app dùng nền be giấy `#f2f0e8`, viền 1px phẳng, không đổ bóng, menu chỉ có chữ, và rất nhiều nhãn chữ hoa monospace giãn chữ. Đó là phong cách "editorial" có chủ ý — nhưng nhìn ra cũ, và **chính nền be là nguyên nhân**: be cùng họ ấm với lime nên hai màu chìm vào nhau, màu nhấn mất hết tác dụng.

**Ba mặt phẳng thay vì hai.** `--paper` là nền trang (xám hơi lạnh), `--paper-raised` là mặt thẻ (**trắng**), `--paper-sunken` là mặt lõm (đầu bảng, cột Kanban). Nền trang không còn là màu trắng, nhờ vậy thẻ nổi lên mà không cần vẽ viền đậm. Lime đặt trên nền lạnh thì bật hẳn lên.

**Bóng đổ hai lớp.** Một lớp 1px sát mép cho cạnh sắc, một lớp toả rộng và mờ cho khối — một lớp duy nhất luôn trông như dán giấy. Ở chế độ tối bóng gần như vô hình, nên chiều sâu chuyển sang dựa vào mặt thẻ sáng hơn nền cộng viền; token `--shadow-*` khai lại riêng cho chế độ tối chứ không dùng chung.

**Chỉ nút chính có bóng.** Trên một màn hình chỉ nên có một việc đáng làm nhất; bóng nâng đúng nút đó lên nên mắt tìm thấy trước khi kịp đọc chữ. Cho mọi nút cùng bóng thì không nút nào nổi lên. Mọi nút lún xuống 1px khi bấm — trên điện thoại không có trạng thái rê chuột, đó là dấu hiệu duy nhất cho biết cú chạm đã ăn.

**Thanh điều hướng có icon** (tự vẽ, không thêm thư viện — dự án vốn đã tự vẽ `ExplusMark`). Sáu mục chữ xếp dọc nhìn như một đoạn văn, phải đọc mới tìm được; có hình thì sau vài ngày người dùng nhớ theo vị trí và hình. Mục đang mở dùng nền lime nhạt kèm viền, không phải chỉ đổi màu chữ.

**Avatar lấy màu từ tên.** Sáu tông, chọn bằng tổng mã ký tự, nên cùng một người luôn ra cùng một màu ở mọi màn hình — nhìn màu là nhận ra người quen trước khi đọc chữ.

**Bỏ nhãn chữ hoa monospace giãn chữ.** Với tiếng Việt kiểu chữ đó đọc chậm hơn hẳn, vì dấu thanh nằm trên chữ hoa trông rất chật. Monospace giữ lại đúng chỗ nó có ích: mã OTP, mã khôi phục, mật khẩu tạm, mã dự án.

##### Hai lỗi tương phản phát hiện khi làm

**Lime làm màu chữ là không đọc được.** `#a9d900` trên nền trắng chỉ đạt khoảng **1,8:1**, dưới xa mức 4,5:1 của WCAG AA — mà liên kết "Tạo việc mới" ở màn "Hôm nay của tôi" đang dùng đúng màu đó. Đã thêm token riêng `--accent-ink` (`#5c7600`, đạt 4,6:1) cho mọi chỗ lime làm chữ; ở chế độ tối token này trỏ về lime sáng vì nền tối đã thừa tương phản.

**`cn` không phải `tailwind-merge`, và điều đó đã âm thầm nuốt một màu.** `Pill` đặt `border-line` ở lớp cơ sở còn `OverdueBadge` truyền `border-danger-line` — nhưng `cn` chỉ **nối chuỗi**, nên thứ tự trong tệp CSS mới quyết định, không phải thứ tự truyền vào. Kiểm tra CSS đã build cho thấy Tailwind sinh `.border-danger-line` **trước** `.border-line`, tức lớp cơ sở thắng và nhãn "Trễ hạn" hiện viền xám thay vì viền đỏ. Không có gì báo lỗi; test cũng không bắt được.

Sửa bằng cách đổi kiến trúc chứ không đổi thứ tự: lớp cơ sở của `Pill` **không chứa màu nào**, toàn bộ bộ ba viền/nền/chữ đi qua một tham số `tone` riêng, nên mỗi viên nhãn chỉ bao giờ có đúng một lớp đặt `border-color`. Bài học ghi lại vì nó áp cho mọi component nhận `className`: **lớp cơ sở không được đặt thuộc tính mà chỗ gọi có quyền ghi đè.**

#### Người dùng tự chọn sáng / tối, và một phím tắt cho cả app

Hai thứ này làm cùng đợt vì cùng một lý do: đây là công cụ người ta mở **tám tiếng mỗi ngày**. Ở nhịp đó, những thứ nhỏ mà lặp lại mới là thứ đáng sửa.

##### Chế độ tối có sẵn, nhưng không ai chọn được

Token màu tối đã viết từ đầu, chỉ là chúng nằm trong `@media (prefers-color-scheme: dark)` — tức là **bám vào cài đặt hệ điều hành**. Máy để sáng mà muốn app tối thì phải đi đổi cài đặt của cả Windows.

Đã chuyển sang `<html data-theme>` với ba lựa chọn: **Sáng · Tối · Theo máy** (mặc định `Theo máy`, giữ nguyên hành vi cũ cho người không quan tâm). Chỗ chọn nằm trong menu tài khoản.

**Cái giá, nói rõ:** cách này cần JavaScript thì mới có màu tối. Chấp nhận được vì đây là app nội bộ sau đăng nhập, vốn đã không chạy được nếu tắt JS.

**Nháy trắng là lỗi phải chặn từ đầu.** Nếu để React gắn `data-theme` sau khi hydrate, người dùng chế độ tối sẽ thấy một nháy trắng mỗi lần tải trang. Nên có một đoạn script đồng bộ nằm thẳng trong `<head>`, chạy trước khung hình đầu tiên. Nó được kiểm bằng sáu trường hợp, gồm cả **lựa chọn của người dùng phải thắng cài đặt của máy** và **localStorage bị chặn** (chế độ riêng tư của Safari ném lỗi khi đọc — không được để cả app trắng màn hình chỉ vì không đọc được một tuỳ chọn màu sắc).

##### Lỗi bắt được: `dark:` của Tailwind vẫn bám vào hệ điều hành

Sau khi đổi xong token, kiểm CSS đã build thì thấy vẫn còn một `@media (prefers-color-scheme: dark)`. Nó đến từ **biến thể `dark:` của Tailwind**, mà `pill.tsx` dùng cho màu avatar.

Hậu quả nếu để nguyên — im lặng và khó lần ra:

> Máy để **tối**, người dùng chọn **sáng** → nền và chữ chuyển sáng đúng, nhưng avatar vẫn giữ kiểu tối. Vài mảng màu lạc lõng trên nền sáng. Không lỗi, không cảnh báo, test không bắt.

Sửa bằng một dòng kéo cả hai về chung một nguồn sự thật:

```css
@custom-variant dark (&:where([data-theme="dark"], [data-theme="dark"] *));
```

Kiểm lại trên CSS đã build: `prefers-color-scheme` còn **0** lần, và `dark:bg-emerald-500/15` biên dịch ra `:where([data-theme=dark], …)`.

##### Bảng lệnh Ctrl+K

Đi tới bất kỳ trang nào bằng cách gõ hai chữ rồi Enter, thay vì rê chuột tìm trong thanh bên. Nó cũng giải một việc thanh bên không giải được: **thanh bên biến mất trên màn hình hẹp** — ở đó điều hướng hiện tại là bấm hamburger, đợi ngăn kéo trượt ra, rồi mới chọn.

- Dựng trên thẻ `<dialog>`, cùng lý do đã ghi ở phần hộp thoại: `showModal()` cho sẵn bẫy tiêu điểm, Esc và chặn nền.
- Danh sách lệnh dùng **chung nguồn với thanh bên** (`visibleNavItems`), nên lọc theo quyền tự đúng và không bao giờ lệch nhau.
- **Gõ không dấu vẫn ra**: `cham cong` → Chấm công. Người Việt gõ nhanh thường không bỏ dấu; thiếu bước này thì người dùng kết luận là ô tìm kiếm hỏng.
- Có nút "Tìm nhanh" ở đầu trang hiện luôn tổ hợp phím (`⌘K` trên Mac, `Ctrl K` trên Windows — đọc từ trình duyệt, không đoán). **Một phím tắt không ai biết là một phím tắt không tồn tại.**

*Chưa làm:* tìm được nội dung — gõ tên một công việc rồi nhảy thẳng tới nó. Việc đó cần một endpoint tìm kiếm ở backend chưa có, nên nhãn ô nhập chỉ hứa đúng phần đang làm được.

##### Menu tài khoản thật, thay cho hàng nút phơi ra

Góc phải trước đây là tên + avatar + nút "Đăng xuất" nằm trần. Cách đó ngốn chiều ngang cho một hành động dùng đúng một lần mỗi ngày, và quan trọng hơn: **không còn chỗ nào để đặt tuỳ chọn của tài khoản**. Gom vào menu thả xuống giải quyết cả hai.

Đi kèm là hook `usePopover` dùng chung cho cả menu này lẫn chuông thông báo. Gom lại vì bản viết tay ở chuông **thiếu phím Esc** — người dùng bàn phím mở menu ra rồi không có cách nào đóng lại ngoài bấm chuột.

### Anti-pattern bị cấm

| Cấm | Thay bằng |
|---|---|
| Nghiệp vụ trong controller | Action trong domain |
| Nghiệp vụ trong model | Action; model chỉ giữ quan hệ, cast, scope |
| Nghiệp vụ trong migration hoặc seeder | Migration chỉ đổi cấu trúc |
| `Auth::user()` bên trong domain | Truyền `User` vào Action như tham số |
| `DB::` hoặc query thô trong controller | Query Object hoặc scope trong domain |
| Truyền `$request->all()` xuống tầng dưới | DTO có kiểu rõ ràng |
| Truy vấn trong vòng lặp (N+1) | Eager loading; bật `Model::preventLazyLoading()` ở môi trường dev |
| Vòng lặp gửi mail/nén ảnh ngay trong request | Đẩy vào queue |
| Số ma thuật, chuỗi ma thuật | Enum hoặc hằng số |
| Xoá cứng dữ liệu liên quan tiền lương | Soft delete + nhật ký kiểm toán |

### Quy ước dữ liệu, thời gian & tiền tệ

Phần này phải chốt **trước migration đầu tiên**. Sai ở đây thì sửa sau là đổi cả dữ liệu đã chạy thật.

| Chủ đề | Quy ước | Vì sao |
|---|---|---|
| **Múi giờ lưu trữ** | Database và code luôn dùng **UTC**. `config/app.php` để `'timezone' => 'UTC'` | Đây là bug kinh điển của hệ thống chấm công: server đổi múi giờ, DST, hoặc deploy sang máy khác là toàn bộ giờ vào/ra lệch |
| **Múi giờ hiển thị** | Đổi sang `Asia/Ho_Chi_Minh` ở **tầng trình bày** (Resource hoặc frontend), không đổi ở Domain | |
| **Mốc thời gian nhận từ client** | Luôn đi qua `App\Support\Time\IncomingDateTime::toUtc()`. Có offset thì tin offset; không có thì hiểu là giờ Việt Nam | Cast `datetime` của Eloquent lưu chuỗi bằng `format()` mà **không đổi về UTC**: gửi kèm offset thì offset bị nuốt, gửi giờ trần thì bị hiểu là UTC. Cả hai đều lệch bảy tiếng và không có gì báo — xem [mục 1.6](#16-deadline--nhắc-việc--đã-xong) |
| **"Ngày làm việc"** | Lưu riêng cột `work_date` kiểu `DATE` theo giờ VN, **không suy ra từ timestamp UTC** | Ca đêm hoặc bấm giờ lúc 00:30 sẽ bị tính nhầm sang ngày hôm trước nếu suy ra từ UTC |
| **Tiền tệ** | `DECIMAL(15,2)`, **tuyệt đối không dùng FLOAT/DOUBLE** | Số thực nhị phân không biểu diễn chính xác được tiền; sai số tích luỹ trên bảng lương là lỗi không thể giải thích với kế toán |
| **Số giờ công** | `DECIMAL(6,2)` hoặc lưu **số phút** kiểu integer | Cùng lý do trên |
| **Khoá chính** | `BIGINT UNSIGNED AUTO_INCREMENT` nội bộ + cột `uuid` để lộ ra API | ID tuần tự lộ ra ngoài cho biết công ty có bao nhiêu bản ghi, và dễ dò |
| **Ngày giờ** | Kiểu `TIMESTAMP`, luôn `nullable` nếu nghiệp vụ cho phép trống | |
| **Xoá** | Soft delete cho mọi bảng nghiệp vụ; **cấm xoá cứng** dữ liệu liên quan công/lương | |
| **Enum** | Lưu dạng chuỗi (`varchar`), không lưu số | Đọc dump database vẫn hiểu; thêm giá trị mới không phải migrate |
| **Khối `@property` trên model** | **Bắt buộc**, liệt kê đủ mọi cột kèm kiểu sau khi cast | Larastan suy kiểu từ migration nên thấy `status` là `string` chứ không thấy nó đã cast sang enum. Thiếu khối này thì phân tích tĩnh mức 8 báo sai hàng loạt, và người đọc mã cũng không biết `due_date` trả về `CarbonImmutable` hay chuỗi |
| **Ngôn ngữ hiển thị** | Toàn bộ chuỗi hiển thị qua `lang/vi/`, kể cả thông báo validate | |
| **Định dạng ngày trên giao diện** | `dd/mm/yyyy` | Chuẩn quen thuộc ở Việt Nam |

### Quy trình phát triển

| Hạng mục | Quy ước |
|---|---|
| Nhánh | `main` (production) ← `develop` ← `feature/<mã>-<mô-tả-ngắn>` |
| Commit | [Conventional Commits](https://www.conventionalcommits.org): `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:` |
| Pull request | Bắt buộc review 1 người; CI xanh mới được merge; squash merge |
| Môi trường | `local` → `staging` → `production`, ba database tách biệt |
| Migration | Chỉ tiến, không sửa migration đã chạy trên production. Cần đổi thì viết migration mới |

**Định nghĩa "xong" (Definition of Done)** — một đầu việc chỉ được tick khi đủ cả 5:

1. Code chạy đúng yêu cầu
2. Có test bao phủ đường thành công **và** đường lỗi
3. Pint, Larastan, Deptrac, Pest, ESLint, `tsc` đều xanh
4. Không phá vỡ quy tắc trong chương kiến trúc này
5. Đã cập nhật tài liệu API nếu có thay đổi endpoint

### Lưu ý về các package

**Đã xác minh** — toàn bộ package đều hỗ trợ Laravel 13, phiên bản thực tế đang cài:

| Package | Bản cài | Ghi chú |
|---|---|---|
| `spatie/laravel-data` | 4.23.0 | DTO |
| `spatie/laravel-model-states` | 2.14.2 | **yêu cầu PHP ^8.4** |
| `spatie/laravel-query-builder` | 7.3.1 | Query object |
| `spatie/laravel-permission` | 8.3.0 | Vai trò & quyền |
| `spatie/laravel-activitylog` | 5.0.0 | **yêu cầu PHP ^8.4** |
| `spatie/laravel-medialibrary` | 11.23.3 | Tệp đính kèm |
| `laravel/sanctum` | 4.3.3 | Xác thực SPA |
| `laravel/horizon` | 5.48.2 | Theo dõi queue |
| `league/flysystem-aws-s3-v3` | 3.35.2 | Driver cho R2 |
| `larastan/larastan` | 3.10 | Phân tích tĩnh |
| `deptrac/deptrac` | 4.7 | Ranh giới kiến trúc |
| `pestphp/pest` | 4.7 | Xem lưu ý dưới |

**Vì sao bắt buộc PHP 8.4:** `laravel-model-states` và `laravel-activitylog` đều yêu cầu `^8.4`. Laravel 13 tự nó chấp nhận 8.3, nhưng bộ package này thì không.

**Pest dừng ở v4, không lên v5:** Pest 5 yêu cầu PHPUnit 13, trong khi skeleton Laravel 13 ghim `phpunit/phpunit: ^12.5`. Pest 4 vẫn có đầy đủ architecture testing nên không ảnh hưởng gì. Nâng lên v5 khi Laravel chuyển sang PHPUnit 13.

Nguyên tắc giữ nguyên: package nào chưa hỗ trợ thì chờ hoặc tự viết phần tối thiểu — **không hạ phiên bản Laravel để chiều package**.

---

## Lộ trình theo đợt

Bốn nhóm chức năng khá độc lập, làm cuốn chiếu để sớm có thứ dùng được thật.

**Lộ trình đã bị làm nhảy cóc theo yêu cầu thực tế** — chấm công (đợt 3), mức lương và thưởng dự án (đợt 5) làm trước khi đợt 1 khép lại. Ghi rõ vì nó tạo ra một hệ quả cần nhớ: mấy phần đó **đang thiếu mảnh của đợt 2 và 4** để hoàn chỉnh, chứ không phải làm dở.

| Đợt | Nội dung | Trạng thái |
|---|---|---|
| **1** | Quản lý task, giao việc, comment, deadline | 🔨 Đang làm — xong 1.1 → 1.9 + nhân sự, tổng quan, giao diện. Kế tiếp: **1.10 vận hành & đưa vào sử dụng** |
| **2** | Báo cáo tiến độ hằng ngày kèm ảnh | 🔄 **Phần chữ đã xong**; ảnh minh chứng chờ Cloudflare R2 |
| **3** | Chấm công, ca làm việc, lịch nghỉ lễ | 🔄 Phần đo giờ **đã xong**; đối chiếu báo cáo và xuất Excel còn chờ |
| **4** | Đơn nghỉ phép, quỹ phép, OT, chốt kỳ công | ⏳ Chờ — **chặn phần tính lương thật** |
| **5** | Thưởng KPI theo dự án, dashboard cho lãnh đạo | 🔄 **Quỹ thưởng dự án và dashboard tổng quan đã xong**; chấm điểm tự động và báo cáo tháng còn chờ |

---

## Đợt 1 — Quản lý Task (đang làm)

**Mục tiêu:** Sếp giao được task cho nhân viên, nhân viên nhận và cập nhật tiến độ, hai bên trao đổi ngay trong task, hệ thống cảnh báo task sắp và đã quá hạn.

**Coi là xong khi:** một quản lý tạo task giao cho nhân viên, nhân viên nhận trên điện thoại, đổi trạng thái, comment qua lại, và cả hai nhận được nhắc việc trước hạn 1 ngày.

### 1.1 Khởi tạo dự án ✅ Đã xong

- [x] Tạo project **Laravel 13.24** trong `backend/`
- [x] Tạo project **Next.js 16.3** (App Router, TypeScript, Tailwind 4) trong `frontend/`
- [x] Docker Compose: PHP 8.4-FPM, Nginx, MySQL 8.4, Redis 7, Horizon, Scheduler
- [x] Cấu hình `.env.example` cho hạ tầng, backend và frontend
- [x] Khởi tạo git repo, `.gitignore`, `.editorconfig`
- [x] **Xác minh tương thích Laravel 13** của toàn bộ package — kết quả ở [Lưu ý về các package](#lưu-ý-về-các-package)

**Dựng khung kiến trúc** (theo [Kiến trúc & quy ước mã nguồn](#kiến-trúc--quy-ước-mã-nguồn)):

- [x] Tạo cây `app/Domain/`, `app/Http/`, `app/Support/`
- [x] Chuyển `User` từ `app/Models/` sang `app/Domain/Identity/Models/`, cập nhật `config/auth.php`, factory, seeder
- [x] `IdentityServiceProvider` và `TaskServiceProvider`, đăng ký ở `bootstrap/providers.php`
- [x] `declare(strict_types=1)` toàn bộ mã nguồn, Pint tự thêm
- [x] `Model::shouldBeStrict()` và `Model::preventLazyLoading()` ở môi trường dev
- [x] `Date::use(CarbonImmutable::class)` — ngày giờ không bị sửa tại chỗ
- [x] Múi giờ: lưu trữ UTC, thêm `config('app.display_timezone')` cho giờ Việt Nam
- [x] Disk `r2` trong `config/filesystems.php` với `visibility => null`
- [x] Enum `TaskStatus` (kèm bảng chuyển trạng thái hợp lệ) và `TaskPriority`
- [x] `App\Support\Exceptions\DomainException` — nền cho dạng lỗi API thống nhất

**Cổng chất lượng** — toàn bộ đã chạy xanh:

| Cổng | Trạng thái |
|---|---|
| Laravel Pint (preset `laravel` + `declare_strict_types`) | ✅ PASS, 33 files |
| Larastan mức 8 | ✅ No errors |
| Deptrac (ranh giới tầng + ranh giới miền) | ✅ 0 violations |
| Pest + Architecture test | ✅ 25 passed, 121 assertions |
| ESLint (cấm `any`) | ✅ PASS |
| Prettier | ✅ PASS |
| `tsc --noEmit` (strict + `noUncheckedIndexedAccess`) | ✅ PASS |
| `next build` | ✅ PASS |

- [x] Cài Laravel Pint, Larastan 3.10, Deptrac 4.7, Pest 4.7
- [x] Bộ **architecture test** 14 luật, chạy trong testsuite riêng
- [x] ESLint + Prettier + TypeScript strict cho frontend, cấm `any`
- [x] Cây `frontend/src/features/`, TanStack Query, Zod, react-hook-form
- [x] `frontend/src/lib/api-client.ts` — HTTP client kèm dạng lỗi thống nhất
- [x] **CI chặn merge nếu fail**: `.github/workflows/ci.yml`
- [x] Cài Laravel Horizon (`horizon:install`), chạy trong container riêng
- [x] Sinh `types/api.ts` từ OpenAPI — `npm run api:types`, sinh từ tài liệu Scramble của mục 1.8

**Đã chạy thử toàn bộ stack**, không chỉ cấu hình trên giấy:

| Kiểm tra | Kết quả |
|---|---|
| 6 container `docker compose up -d` | ✅ app, nginx, mysql, redis, horizon, scheduler đều Up |
| `GET http://localhost:8000` qua Nginx → PHP-FPM → Laravel | ✅ HTTP 200 |
| Horizon | ✅ `Horizon started successfully` |
| Scheduler | ✅ `Running scheduled tasks` |
| Migration trên MySQL | ✅ 3 bảng gốc chạy xong |

#### Quyết định phát sinh khi triển khai

Bốn điều chỉnh so với kế hoạch ban đầu, phát hiện trong lúc làm:

**PHP 8.4 chứ không phải 8.3.** Laravel 13 chấp nhận 8.3, nhưng `spatie/laravel-model-states` 2.14 và `spatie/laravel-activitylog` 5.0 đều yêu cầu `^8.4`. Container dùng 8.4, `composer.json` đặt `"php": "^8.4"`.

**Test chạy trên MySQL, không dùng SQLite in-memory.** Mặc định của Laravel là SQLite vì nhanh, nhưng hệ thống này tính tiền lương và giờ công — nơi SQLite và MySQL khác nhau ở đúng chỗ dễ sinh lỗi: độ chính xác `DECIMAL`, hàm ngày giờ, strict mode, ràng buộc khoá ngoại. Test xanh trên SQLite mà hỏng trên production là kịch bản tệ nhất. Đánh đổi là test chậm hơn và cần MySQL chạy sẵn.

**Identity là shared kernel.** Quy tắc "Domain A không gọi thẳng Domain B" gặp thực tế ngay: `Task` bắt buộc phải tham chiếu `User` qua `assignee_id`. Nên `Identity` được đặt làm ngoại lệ — mọi miền được phụ thuộc nó, nó không phụ thuộc miền nào. Ghi rõ trong `deptrac.yaml` và bộ architecture test.

**Font Inter thay Geist.** Geist chỉ có subset `latin`/`latin-ext`, thiếu một số tổ hợp dấu tiếng Việt. Inter có subset `vietnamese`.

**Entrypoint nới quyền ghi cho `storage/` và `bootstrap/cache`.** Phát hiện khi chạy thử thật. Bind mount trên Windows và macOS gắn thư mục vào container dưới quyền `root:root 755`, trong khi php-fpm chạy dưới `www-data` — nên Blade không ghi được view đã biên dịch.

Triệu chứng cực kỳ dễ chẩn đoán sai: PHP 8.4 làm `tempnam()` rơi về `/tmp` rồi phát `E_WARNING`, Laravel ở chế độ debug nâng nó thành exception, và **mọi** request trả 500 với thông báo `tempnam(): file created in the system's temporary directory` — không hề nhắc gì tới quyền file. Tệ hơn nữa, trang nào đã có view biên dịch sẵn thì vẫn chạy bình thường, nên lỗi trông như chỉ xảy ra ở vài route.

Cách xử lý: `docker/php/entrypoint.sh` chạy `chmod -R a+rwX` lên đúng hai thư mục đó mỗi lần container khởi động, rồi nối tiếp `docker-php-entrypoint` gốc. **Không** chạy php-fpm bằng root.

### 1.2 Xác thực & phân quyền

- [x] Sanctum SPA authentication bằng cookie phiên httpOnly (`statefulApi()`)
- [x] `spatie/laravel-permission` với enum `Role` và `Permission` — gõ sai tên quyền là lỗi biên dịch, không phải `false` âm thầm
- [x] Bốn vai trò `admin` / `giam_doc` / `truong_phong` / `nhan_vien` + 12 quyền, `RolePermissionSeeder` chạy lại nhiều lần không nhân đôi
- [x] **Định dạng lỗi API thống nhất** — `ApiExceptionRenderer`, hợp đồng `{message, code, errors}` với frontend
- [x] `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`
- [x] `PATCH /auth/password` — người dùng tự đổi, thu hồi mọi phiên cũ
- [x] `GET /users`, `POST /users` — quản trị người dùng (API)
- [x] `POST /users/{uuid}/deactivate` — vô hiệu hoá, **không xoá**; chặn tự khoá chính mình
- [x] `POST /users/{uuid}/reset-password` — admin đặt lại hộ, mật khẩu tạm hiện một lần
- [x] Middleware `active` — chặn tài khoản nghỉ việc **ngay giữa phiên**, không chỉ lúc đăng nhập
- [x] Rate limit đăng nhập 5 lần/5 phút theo cặp email + IP
- [x] `login_attempts` — nhật ký mọi lần đăng nhập thành công lẫn thất bại kèm IP và thiết bị
- [x] `UserPolicy` — phân quyền theo từng bản ghi
- [x] Chính sách mật khẩu tối thiểu 12 ký tự + kiểm tra mật khẩu đã lộ (`uncompromised`)
- [x] Trang đăng nhập ở frontend + route guard (`src/proxy.ts`)
- [x] **Xác thực hai lớp bằng mã OTP — bắt buộc với mọi nhân viên** (xem mục riêng bên dưới)
- [x] **Màn hình** quản trị người dùng — trang `/employees`, xem [Quản trị nhân sự](#quản-trị-nhân-sự--đã-xong)

### 1.2b Xác thực hai lớp (OTP) ✅ Đã xong

**Bắt buộc với toàn bộ nhân viên** — không thiết lập thì không đăng nhập được. Đây là mở rộng so với kế hoạch ban đầu (README cũ chỉ yêu cầu với `admin`).

**Kênh mặc định: email OTP** — gửi mã 6 số tới hộp thư. Công ty chọn hướng này để nhân viên không phải cài thêm ứng dụng.

Đổi kênh bằng một biến, không phải sửa mã nguồn:

```
TWO_FACTOR_DRIVER=email   # gửi mã qua email (mặc định)
TWO_FACTOR_DRIVER=totp    # ứng dụng xác thực (Google/Microsoft Authenticator)
```

| | email (đang dùng) | totp |
|---|---|---|
| Nhân viên phải cài app | Không | Có |
| Chi phí | Phí dịch vụ mail | 0đ |
| Phụ thuộc | Nhà cung cấp mail; thư vào spam là không đăng nhập được | Không, chạy offline |
| Độ mạnh | Yếu hơn — ai chiếm được hộp thư là chiếm được tài khoản | Mạnh hơn |

#### ✅ Đã chốt (24/08/2026): **giữ email OTP**

Công ty quyết định tiếp tục dùng email. TOTP vẫn nằm nguyên trong mã, đã có test, và `isEnrolled()` khiến việc bật sau này không khoá ai — đổi một dòng `.env` là xong, không phải viết thêm gì.

**Ghi rõ điểm yếu để sau này không ai tưởng đây là lựa chọn mặc nhiên:** với email OTP, **hòm thư trở thành chìa khoá vạn năng**. Ai vào được email của một nhân viên thì vừa đọc được mã xác thực, vừa dùng được luồng quên mật khẩu — hai lớp bảo vệ sập cùng lúc, vì chúng đi chung một đường.

Nên lựa chọn này **chỉ đúng khi email công ty tự nó đã có 2 lớp**. Nếu nhân viên dùng Gmail cá nhân không bật xác minh 2 bước thì lớp thứ hai của hệ thống không thật sự là lớp thứ hai.

Hai mốc nên xem lại quyết định:

- Khi hệ thống bắt đầu giữ **dữ liệu lương** (đợt 4) — ít nhất chuyển `admin`, kế toán, HR sang TOTP
- Khi công ty vượt khoảng 20 người — lúc đó việc hướng dẫn từng người cài app không còn gọn như bây giờ

Cả hai kênh nằm sau interface `TwoFactorProvider`. Interface nhận `User` chứ không nhận chuỗi secret — bản đầu tôi ký theo secret cho vừa TOTP, và nó **không vừa** với email OTP (kênh này sinh mã mới mỗi lần, tra trong bảng `two_factor_codes`, không có secret cố định). Đây là bài học đáng ghi: hình dạng interface chỉ đúng khi có ít nhất hai cài đặt thật.

**Luồng:**

```
nhập mật khẩu ──┬─ đã bật OTP  ──→ nhập mã ─────────────────────────→ vào app
                └─ chưa bật    ──→ quét QR → nhập mã → lưu mã khôi phục → vào app
```

- [x] `POST /auth/login` — mật khẩu đúng **không** tạo phiên, chỉ trả `two_factor_required` hoặc `two_factor_setup_required`
- [x] `POST /auth/two-factor-challenge` — nhập mã hoặc mã khôi phục
- [x] `GET /auth/two-factor/setup` — email: gửi mã đầu tiên; totp: trả mã QR
- [x] `POST /auth/two-factor/confirm` — xác nhận lần đầu, trả 8 mã khôi phục
- [x] `POST /auth/two-factor/resend` — gửi lại mã, giới hạn 3 lần / 5 phút
- [x] `POST /users/{uuid}/reset-two-factor` — quản trị viên gỡ hộ khi nhân viên mất quyền truy cập
- [x] Mã OTP **lưu dạng băm** trong `two_factor_codes`; secret TOTP và mã khôi phục **mã hoá** (`encrypted` cast)
- [x] Rate limit riêng cho bước nhập mã và cho nút gửi lại, không dùng chung khoá với bước mật khẩu
- [x] Giao diện 4 bước, kèm màn bắt buộc lưu mã khôi phục trước khi vào app
- [x] **Mailpit** trong `docker-compose` — hộp thư giả ở `localhost:8025` để đọc mã khi dev

**Bốn chi tiết quyết định hệ thống có dùng được không:**

**Mã khôi phục.** Bắt buộc OTP toàn công ty nghĩa là mất quyền truy cập hộp thư = mất tài khoản. 8 mã dùng một lần, hiện đúng một lần, giao diện buộc tích xác nhận đã lưu trước khi cho vào app. Cộng với đường admin gỡ hộ. Thiếu hai thứ này thì tuần đầu HR ngập trong cuộc gọi.

**Chỉ bật sau khi nhập đúng mã lần đầu.** Bật ngay khi vừa gửi mã thì người không nhận được email sẽ bị khoá ngoài vĩnh viễn. `two_factor_confirmed_at` là thứ quyết định đã bật hay chưa.

**Gửi mã mới thì mã cũ chết ngay.** Không làm vậy thì mọi mã từng gửi đều còn sống tới lúc hết hạn — càng bấm "gửi lại" càng nhiều mã hợp lệ cùng lúc.

**Không lưu mã dạng rõ.** Mã OTP là thông tin xác thực; ai đọc được dump database không được phép đăng nhập thay người khác. Ngay cả test cũng phải đọc mã từ email đã gửi chứ không từ database.

#### Gửi mã qua hàng đợi — sửa lại một quyết định sai

Bản đầu cố tình gửi mã **đồng bộ**, với lý do: người dùng đang đứng chờ trước màn nhập mã, hàng đợi tắc là họ bấm "gửi lại" liên tục. Lý do đó vẫn đúng, nhưng cách giải quyết thì sai — gửi đồng bộ nghĩa là request đăng nhập ôm trọn vòng đi-về SMTP với Gmail: bắt tay TLS, xác thực, gửi, đóng.

Đo thật trên máy dev, `POST /api/v1/auth/login` qua Nginx với SMTP Gmail thật:

| Cách gửi | Lần 1 (nguội) | Các lần sau |
|---|---|---|
| Đồng bộ | 7,19s | 3,95s · 4,04s · 4,69s |
| Qua hàng đợi | 2,27s | 0,52s · 0,49s |

Gần **4 giây ở mọi lần đăng nhập** — chính là thứ đang cố tránh, chỉ dời sang chỗ khác và tệ hơn, vì nó xảy ra luôn chứ không phải khi hàng đợi tắc. (Con số này cũng cho thấy ước lượng "1–3 giây" ban đầu của tôi là quá lạc quan; phải đo mới biết.)

**Nỗi lo cũ được xử lý bằng hàng đợi riêng, không bằng việc bỏ hàng đợi.** `two-factor.queue` (mặc định `auth`) đứng **đầu** danh sách ưu tiên của Horizon. Không có nó, một đợt quét deadline đẩy hai trăm email vào hàng sẽ khiến mã OTP của người đang đứng ở màn đăng nhập xếp sau tất cả — đúng kịch bản xấu mà bản đầu lo ngại. Job mang mã ở dạng rõ, nên hàng đợi bắt buộc là Redis nội bộ, không đẩy sang dịch vụ bên thứ ba.

**Phần ghi mã vào database vẫn chạy đồng bộ.** Chỉ việc gửi thư đi hàng đợi. Nếu đẩy cả phần ghi thì có lúc người dùng đã ở màn nhập mã trong khi bản ghi chưa tồn tại — nhập đúng mã trong hộp thư vẫn bị báo sai.

**Chữ trên màn hình đổi theo.** "Chúng tôi *vừa gửi* mã" thành "*Đang gửi* mã… Thư thường tới sau vài giây". Nói "đã gửi" trong khi hộp thư còn trống là đẩy người dùng đi bấm "gửi lại" — thành ra chậm hơn cả chờ.

**Test phải bắt được nếu ai đó lỡ tay gỡ `ShouldQueue`.** Đã kiểm chứng bằng cách gỡ thật: 4/6 test trong `OtpDeliveryTest` đỏ. Chốt chặn quan trọng nhất là `Mail::assertNothingSent()` — thiếu nó thì mail quay về gửi đồng bộ mà mọi test khác vẫn xanh. Có thêm một test đọc thẳng `config/horizon.php` để giữ `auth` ở vị trí đầu tiên, vì đó là thứ dễ bị xô lệch khi thêm hàng đợi mới. Lưu ý vận hành lặp lại: **đổi cấu hình hàng đợi phải khởi động lại Horizon**.

Cổng chất lượng sau thay đổi này: Pest ✅ **334 passed** (3530 assertions) · Pint · Larastan mức 8 · Deptrac ✅ sạch · ESLint / Prettier / `tsc` / `next build` ✅ PASS. Thêm **6 test** trong `tests/Feature/Http/Auth/OtpDeliveryTest.php`.

#### Kết quả kiểm thử

*(Số liệu dưới đây là của mục 1.2b lúc mới làm xong, giữ nguyên làm mốc lịch sử.)*


| Cổng | Kết quả |
|---|---|
| Pest | ✅ **121 passed** (389 assertions) |
| Laravel Pint | ✅ PASS, 131 files |
| Larastan mức 8 | ✅ No errors |
| Deptrac | ✅ 0 violations |
| ESLint / Prettier / `tsc` / `next build` | ✅ PASS |

#### Quyết định phát sinh khi làm 1.2

**Next.js 16 đổi tên Middleware thành Proxy.** File route guard phải đặt tại `src/proxy.ts`. Viết `middleware.ts` theo thói quen thì file **không chạy và không có cảnh báo nào** — bug im lặng đúng nghĩa. Phát hiện nhờ đọc tài liệu đi kèm trong `node_modules/next/dist/docs` thay vì viết theo trí nhớ.

Tài liệu cũng nói rõ Proxy **không phải** lớp phân quyền: nó chỉ kiểm tra lạc quan sự tồn tại của cookie phiên để người chưa đăng nhập không phải tải cả trang. Phân quyền thật nằm ở backend, cookie giả không lừa được server.

**`Model::preventLazyLoading()` bắt được N+1 thật ngay lần đầu.** Mỗi lần gọi `$user->can(...)` đều đọc quan hệ `roles`/`permissions`. Không nạp trước thì ở production thành N+1 âm thầm ở **mọi endpoint có kiểm quyền**. Đã nạp sẵn một lần cho cả request trong middleware `active`. Đây chính là lý do bật `preventLazyLoading` từ mục 1.1.

**Controller tách thành một hành động một file.** Arch test bắt được `AuthController` có các phương thức `login`/`logout`/`me`/`changePassword` — preset Laravel chỉ chấp nhận tên RESTful. Thay vì nới luật, đã tách thành `LoginController`, `LogoutController`, `MeController`, `ChangePasswordController`, `DeactivateUserController`, `ResetUserPasswordController` với `__invoke`. Hợp với triết lý Action của dự án hơn.

**Đặt tên cookie phiên cố định** `SESSION_COOKIE=task_session`. Mặc định Laravel suy tên cookie từ `APP_NAME`, mà `APP_NAME` tiếng Việt có dấu cho ra tên khó đoán — trong khi `src/proxy.ts` phải đọc đúng tên đó.

**Route guard không được dùng cookie phiên làm tín hiệu "đã đăng nhập".** Phát hiện khi chạy thử trên trình duyệt: màn hình trắng tinh, không lỗi gì trong console.

Nguyên nhân: Laravel cấp cookie phiên (`explus_session`) cho **mọi người**, kể cả khách vừa mở trang đăng nhập. Route guard coi sự tồn tại của nó là "đã đăng nhập" nên sinh ra vòng lặp:

```
/login  ──(có cookie phiên)──→  /
   ↑                            │
   └────(/auth/me trả 401)──────┘        lặp vô tận
```

Đã sửa bằng cờ riêng `explus_auth`, chỉ đặt khi đăng nhập **thật sự xong cả hai bước**, xoá khi đăng xuất. Cờ này không mã hoá và không chứa gì bí mật — nó chỉ trả lời "có nên hiển thị trang hay đá về đăng nhập", còn phân quyền thật vẫn ở `auth:sanctum` + `active` + Policy.

Hai lớp bảo vệ chống lặp, vì một mình cờ chưa đủ:
- Proxy **không** đá người có cờ từ `/login` về `/` — cờ có thể cũ hơn phiên phía server
- Frontend **xoá cờ trước** khi chuyển về `/login` lúc gặp 401

`AuthFlagCookieTest` khoá lại hành vi này bằng 4 test.

**CORS phải khai báo tay — mặc định của Laravel làm hỏng đăng nhập bằng cookie.** Phát hiện khi chạy thử thật qua trình duyệt. Mặc định trả `Access-Control-Allow-Origin: *` và **không có** `Access-Control-Allow-Credentials`. Chuẩn CORS cấm dùng `*` cùng lúc với credentials, nên trình duyệt **chặn phản hồi dù server đã trả 200**.

Loại lỗi này rất khó truy: `curl` chạy bình thường vì curl không thực thi CORS; toàn bộ test Pest cũng xanh vì chúng chạy trong cùng process. Chỉ lộ ra khi mở trình duyệt thật. Đã thêm `config/cors.php` khai báo đúng origin và bật `supports_credentials`.

⚠️ **Khi deploy lên domain thật phải sửa ba biến cùng lúc**, thiếu một cái là đăng nhập hỏng mà không rõ vì sao: `FRONTEND_URL` (dùng bởi CORS), `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`.

**Thông báo đăng nhập sai cố tình mơ hồ.** Không nói "email không tồn tại" — đó là cách liệt kê tài khoản có thật. Action cũng băm một lần cả khi không tìm thấy user, để thời gian phản hồi hai trường hợp gần như nhau; chênh lệch thời gian cũng là một kênh dò.

**`uncompromised()` tắt ở môi trường test.** Luật này gọi API HaveIBeenPwned; bật trong test sẽ khiến test phụ thuộc mạng và tự dưng đỏ khi API đó chậm. Production vẫn bật đầy đủ kèm yêu cầu ký tự đặc biệt.

### 1.3 Mô hình dữ liệu ✅ Đã xong

Hệ thống **tự quản lý toàn bộ dữ liệu nhân sự**, không đọc và không đồng bộ từ hệ thống nào khác. Xem [Quyết định kiến trúc đã chốt](#quyết-định-kiến-trúc-đã-chốt).

Làm theo TDD: mỗi vòng viết test đỏ trước, xem nó fail đúng lý do, rồi mới implement.

**Nhân sự & cơ cấu tổ chức** — nền của mọi thứ còn lại:

- [x] `users` — họ tên, email, mật khẩu, `employee_code`, số điện thoại, `joined_at`, `is_active`, `terminated_at`, `department_id`, `position_id`, `manager_id`
- [x] `departments` — có `parent_id` dựng **cây cấp trên–cấp dưới**; `descendantIds()` và `subtreeIds()` trả về phạm vi quản lý ở mọi độ sâu bằng **một truy vấn**
- [x] `positions` — chức vụ kèm `level` (cấp bậc)
- [x] `teams` + `team_user` — đội nhóm liên phòng ban, ràng buộc unique ở tầng database
- [x] Seeder cơ cấu tổ chức và tài khoản quản trị đầu tiên, chạy lại nhiều lần không tạo trùng
- [x] `php artisan users:import <file.csv>` — nhập nhân viên, có `--dry-run`
**Dự án & công việc:**

- [x] `projects` + `project_user` — thành viên dự án kèm `role` riêng trong dự án (`ProjectRole`: quản lý / thành viên / chỉ xem), tách khỏi vai trò hệ thống
- [x] `tasks` — bảng lõi:
  - `title`, `description`, `project_id` (nullable — cho việc vặt ngoài dự án), `parent_task_id`
  - `assignee_id`, `assigner_id`, `reviewer_id` — đều `nullOnDelete` để người nghỉ việc không xoá mất task
  - `status`, `priority` (cast sang enum), `due_date`, `started_at`, `completed_at`
  - `estimate_hours` **DECIMAL(6,2)**, `progress_percent`, `due_date_change_count`
  - `created_by`, `updated_by`, soft delete
- [x] `task_comments` — trả lời lồng nhau qua `parent_id`, `edited_at`, xoá mềm
- [x] `task_user` — người theo dõi task, unique ở tầng database
- [x] `task_activities` — nhật ký `event` + `old_values`/`new_values` dạng JSON, chỉ ghi thêm
- [x] `task_labels` + `task_task_label` — nhãn phân loại kèm mã màu
- [x] **`task_due_date_changes`** — `reason` là **NOT NULL ở tầng database**, không chỉ ở tầng ứng dụng
- [x] Index tổ hợp: `(assignee_id, status, due_date)`, `(project_id, status)`, `(due_date, status)` cho job quét deadline
- [x] `DemoDataSeeder` — dữ liệu mẫu để dev và demo, chặn chạy trên production
- [x] Factory cho toàn bộ 12 model
- [x] Tệp đính kèm — dời sang [mục 1.5](#15-bình-luận--trao-đổi--đã-xong), đã làm ở đó, xem [ghi chú bên dưới](#quyết-định-phát-sinh-khi-làm-13)

**Trạng thái task:** `todo` → `in_progress` → `review` → `done`, cộng `cancelled` và `on_hold`. Chuyển trạng thái đi qua một lớp kiểm tra hợp lệ, không cho nhảy tuỳ tiện.

**Mức ưu tiên:** `low`, `normal`, `high`, `urgent`.

**Đổi deadline phải có lý do và được duyệt.** Đây là ràng buộc nghiệp vụ quan trọng nhất của đợt 1: toàn bộ hệ thống đánh giá đúng hạn ở đợt 5 dựa trên deadline. Nếu ai cũng tự dời hạn khi sắp trễ thì mọi chỉ số về sau đều vô nghĩa. Nên:

- Chỉ **người giao task** hoặc cấp trên mới được đổi hạn, người làm chỉ được **đề nghị**
- Mọi lần đổi đều ghi vào `task_due_date_changes` kèm lý do bắt buộc
- Chi tiết task hiển thị công khai *"đã dời hạn 3 lần"*

**Vòng đời nhân sự** — phải xử lý ngay ở tầng dữ liệu, không để tới lúc có người nghỉ việc mới nghĩ:

- Nhân viên nghỉ việc: `is_active = false`, thu hồi toàn bộ token, task đang làm dở **không được biến mất** mà chuyển sang trạng thái cần bàn giao
- Nhân viên chuyển phòng ban: giữ nguyên lịch sử task cũ, quyền xem đổi theo phòng mới
- Người giao task nghỉ việc: task vẫn còn, hiển thị người giao đã nghỉ

#### Kết quả kiểm thử

| Cổng | Kết quả |
|---|---|
| Pest | ✅ **81 passed** (238 assertions), chạy trên MySQL |
| Laravel Pint | ✅ PASS, 81 files |
| Larastan mức 8 | ✅ No errors |
| Deptrac | ✅ 0 violations |
| `migrate:fresh --seed` trên database thật | ✅ 11 migration + 2 seeder chạy xong |
| `db:seed --class=DemoDataSeeder` | ✅ 1 dự án, 4 task, 5 nhân viên |

#### Quyết định phát sinh khi làm 1.3

**Một bug thật mà 77 test không bắt được.** `DatabaseSeeder` mặc định của Laravel dùng trait `WithoutModelEvents`, trong khi `HasUuid` sinh uuid qua sự kiện `creating`. Tắt sự kiện đi thì cột uuid rỗng và **mọi insert chết với lỗi `Field 'uuid' doesn't have a default value`** — nhưng toàn bộ test model vẫn xanh, vì factory không đi qua seeder. Chỉ lộ ra khi chạy `migrate:fresh --seed` thật.

Đã gỡ trait đó và viết `SeederTest` để không tái diễn. Hệ quả cần nhớ: khi thêm Observer có tác dụng phụ ra ngoài (gửi thông báo, gọi API), Observer đó phải tự bỏ qua khi đang chạy seeder — **không** giải quyết bằng cách tắt sự kiện.

**Nhập nhân viên bằng CSV chứ không phải .xlsx.** Đọc `.xlsx` cần kéo PhpSpreadsheet vào dự án, trong khi Excel lưu sang "CSV UTF-8" chỉ mất hai cú nhấp. Đợt 3 sẽ cần PhpSpreadsheet để xuất bảng công cho kế toán — lúc đó bổ sung đọc `.xlsx` sau. Lệnh đã xử lý sẵn **BOM UTF-8** mà Excel chèn vào đầu file: không gỡ thì mã nhân viên đầu tiên dính ký tự vô hình và mọi lần nhập sau đều tạo bản ghi trùng.

**Tệp đính kèm dời sang mục 1.5.** Kế hoạch ban đầu có bảng `task_attachments` riêng, nhưng `spatie/laravel-medialibrary` đã cài ở 1.1 và làm đúng việc đó tốt hơn (đa hình cho cả task lẫn comment, tự sinh thumbnail — thứ đợt 2 cần). Dựng bảng riêng bây giờ là làm hai lần. Sẽ gắn `HasMedia` vào `Task` và `TaskComment` ở mục 1.5, nơi upload thật sự xảy ra.

**`Data/` chứa cả DTO đầu vào lẫn đối tượng kết quả.** Quy ước đặt tên trong README ghi "DTO = danh từ + `Data`". Thực tế `ImportUsersResult` đọc tự nhiên hơn `ImportUsersResultData` nhiều. Nên quy ước nới thành: DTO đầu vào kết thúc bằng `Data`, đối tượng kết quả kết thúc bằng `Result`.

**Tránh đặt tên phương thức bắt đầu bằng `scope`.** `Department::scopeIds()` tưởng vô hại nhưng Eloquent coi mọi phương thức `scopeXxx` là query scope và gọi nó với tham số `Builder` — gọi trực tiếp trên instance sẽ lỗi. Đổi thành `subtreeIds()`.

### 1.4 API Task ✅ Đã xong

**Chuẩn chung — chốt trước khi viết endpoint đầu tiên, sửa sau là đụng vào mọi màn hình frontend:**

- [x] Chuẩn hoá đường dẫn `/api/v1/...`, dùng API Resource của Laravel
- [x] **Định dạng lỗi thống nhất** — `ApiExceptionRenderer` trả về `{message, code, errors}` cho mọi lỗi API
- [x] **Chuẩn phân trang thống nhất** — offset kèm `meta`/`links`, mặc định 25 dòng, trần 100
- [x] Chuẩn định dạng thời gian trả về: ISO 8601 kèm offset, để frontend tự đổi sang giờ VN
- [x] `GET /tasks` — lọc theo trạng thái / ưu tiên / người làm / dự án / quá hạn / khoảng hạn / từ khoá
- [x] `GET /tasks/{id}` — chi tiết kèm dự án, người liên quan, nhãn, số task con và số bình luận
- [x] `POST /tasks` — tạo và giao task
- [x] `PATCH /tasks/{id}` — cập nhật thông tin
- [x] `PATCH /tasks/{id}/status` — đổi trạng thái riêng, có kiểm tra luồng hợp lệ
- [x] `PATCH /tasks/{id}/assign` — giao lại cho người khác
- [x] `DELETE /tasks/{id}` — xoá mềm
- [x] `PATCH /tasks/{id}/due-date` — đổi hạn, **bắt buộc kèm lý do**, ghi vào `task_due_date_changes`
- [x] `GET /tasks/my` — task của tôi, gom nhóm theo hạn: quá hạn / hôm nay / tuần này / xa hơn
- [x] `GET /tasks/team` — task của cấp dưới theo cây tổ chức, cho quản lý
- [x] `POST /tasks/bulk-reassign` — bàn giao hàng loạt khi nhân viên nghỉ việc hoặc nghỉ dài
- [x] `GET /projects` + CRUD dự án + thành viên dự án
- [x] Form Request validate cho mọi endpoint ghi
- [x] Policy phân quyền theo từng bản ghi (nhân viên chỉ thấy task của mình và của phòng mình)
- [x] Observer ghi `task_activities` tự động khi model thay đổi
- [x] Rate limit cho các endpoint ghi — làm ở [mục 1.9](#19-bảo-mật--đã-xong)

Danh sách bình luận và tệp đính kèm trong chi tiết task đã làm ở [mục 1.5](#15-bình-luận--trao-đổi--đã-xong), nơi hai thứ đó mới thực sự tồn tại.

#### Endpoint đã có

| Nhóm | Đường dẫn |
|---|---|
| Công việc | `GET/POST /tasks` · `GET/PATCH/DELETE /tasks/{id}` |
| Hành động riêng | `PATCH /tasks/{id}/status` · `/assign` · `/due-date` |
| Màn hình làm việc | `GET /tasks/my` · `GET /tasks/team` · `POST /tasks/bulk-reassign` |
| Nhật ký | `GET /tasks/{id}/activities` — thêm ở mục 1.7 |
| Dự án | `GET/POST /projects` · `GET/PATCH/DELETE /projects/{id}` |
| Thành viên dự án | `GET/POST /projects/{id}/members` · `DELETE /projects/{id}/members/{user}` |
| Danh bạ | `GET /users/assignable` — thêm ở mục 1.7 |

#### Kết quả kiểm thử

| Cổng | Kết quả |
|---|---|
| Pest | ✅ **197 passed** (612 assertions), chạy trên MySQL |
| Laravel Pint | ✅ PASS |
| Larastan mức 8 | ✅ No errors |
| Deptrac | ✅ 0 violations |

Riêng mục 1.4 thêm **58 test**: phạm vi xem (10), CRUD và các hành động (18), màn hình làm việc và nhật ký (11), dự án và thành viên (19).

#### Quyết định phát sinh khi làm 1.4

**Phạm vi xem là một scope, không phải điều kiện rải rác trong controller.** `Task::scopeVisibleTo()` và `Project::scopeVisibleTo()` là **ràng buộc bảo mật, không phải bộ lọc tiện ích** — quên gọi một lần ở một endpoint là lộ task hoặc dự án toàn công ty. Gom vào một chỗ thì chỉ có một chỗ để đọc kỹ và một chỗ để kiểm thử.

Người được giao task trong một dự án cũng thấy dự án đó, dù không phải thành viên: không thì họ nhìn task mà không hiểu mình đang làm cho việc gì.

**Xoá cứng task làm Observer ném lỗi khoá ngoại — lỗi thật, test cũ bắt được.** `TaskActivityObserver::deleted()` ghi một dòng nhật ký trỏ tới task vừa bị xoá. Với xoá mềm thì đúng; với `forceDelete()` thì dòng task đã biến mất thật và insert vi phạm khoá ngoại, trả 500. Đã thêm `isForceDeleting()` để bỏ qua, và một test riêng cho đúng tình huống này.

**Một test rung do phụ thuộc ngày trong tuần.** Test gom "việc của tôi" tạo task với hạn `now()->addDays(3)` rồi mong nó rơi vào nhóm *tuần này*. Chạy thứ Hai thì xanh, chạy thứ Sáu thì đỏ vì `addDays(3)` vượt qua `endOfWeek()`. Đã cố định thời gian bằng `travelTo()` về một thứ Hai. Test phụ thuộc ngày chạy là test không dùng được để tin.

**Model phải có khối `@property`.** Larastan suy kiểu thuộc tính từ migration nên thấy `status` là `string`, không thấy nó đã cast sang enum, và `due_date` là `string` chứ không phải `CarbonImmutable`. Thiếu khối này thì phân tích tĩnh mức 8 báo sai hàng chục chỗ và người đọc mã cũng không biết thuộc tính trả về kiểu gì. Đã bổ sung cho `Task`, `Project`, `TaskActivity` — **model mới phải làm theo**.

**Lọc bằng khoá ngoại, không bằng `whereHas` trên uuid.** Ban đầu lọc `assignee_id` bằng `whereHas('assignee', fn ($u) => $u->where('uuid', ...))`. Đổi thành đổi uuid sang khoá chính trước rồi lọc thẳng trên cột: dùng đúng index `(assignee_id, status, due_date)` thay vì nối bảng chỉ để đối chiếu một uuid. Bên trong closure của `whereHas`, tham số cũng chỉ còn là `Builder` chung nên phân tích tĩnh không kiểm được tên cột — gõ sai sẽ chỉ phát hiện lúc chạy.

**Đường dẫn tĩnh phải khai báo trước đường dẫn có tham số.** `/tasks/my` và `/tasks/team` đặt sau `/tasks/{task}` thì Laravel hiểu "my" là uuid và trả 404.

**Dự án đã đóng không nhận việc mới.** `ProjectStatus::isOpen()` giờ được dùng thật trong `StoreTaskRequest`: dự án đã hoàn thành hoặc đã huỷ mà vẫn nhận task mới thì mọi báo cáo tiến độ dự án về sau đều sai.

**Chủ dự án sửa được dự án của mình, nhưng không xoá được.** Giao dự án cho một nhân viên phụ trách là chuyện bình thường; bắt họ xin quyền `project.manage` toàn hệ thống chỉ để đổi mô tả là vô lý. Nhưng xoá là giấu đi cả một mảng công việc của nhiều người — nặng hơn hẳn, nên vẫn cần quyền quản lý.

**Vai trò thành viên đọc qua `Project::memberRoles()`, không qua `$user->pivot->role`.** Thuộc tính `pivot` không khai báo được kiểu nên phân tích tĩnh không kiểm được tên cột. Phương thức này trả về mảng `int => string` trong một truy vấn, và giữ tên bảng nối nằm trong miền Task thay vì rò lên tầng Http.

### 1.5 Bình luận & trao đổi ✅ Đã xong

- [x] `GET /tasks/{id}/comments` — danh sách, phân trang, kèm trả lời và đính kèm
- [x] `POST /tasks/{id}/comments` — viết bình luận, trả lời một cấp
- [x] `PATCH /comments/{id}` / `DELETE /comments/{id}` — sửa, xoá mềm, giữ vết
- [x] Nhắc tên `@` — dò nội dung, lưu vào bảng riêng, thêm người được nhắc vào danh sách theo dõi
- [x] Tự động thêm người bình luận vào danh sách theo dõi task
- [x] Upload đính kèm: giới hạn dung lượng, danh sách trắng theo nội dung thật, đổi tên khi lưu
- [x] Job nền sinh thumbnail sau khi upload, chạy ở hàng đợi riêng `media`
- [x] Giao diện: khu vực trao đổi trong trang chi tiết task, ô soạn thảo có gợi ý nhắc tên
- [x] **Gửi thông báo cho người được nhắc** — làm ở [mục 1.6](#16-deadline--nhắc-việc--đã-xong), đọc từ bảng `task_comment_mentions`
- [x] **Lưu lên Cloudflare R2** — đã bật `MEDIA_DISK=r2` ngày 24/08/2026, kiểm chạy thật: tải lên, ký đường dẫn có hạn, tải về `200`

#### Endpoint đã có

| Nhóm | Đường dẫn |
|---|---|
| Bình luận | `GET/POST /tasks/{id}/comments` · `PATCH/DELETE /comments/{id}` |
| Đính kèm | `POST /comments/{id}/attachments` · `DELETE /comments/{id}/attachments/{media}` |

#### Kết quả kiểm thử

| Cổng | Kết quả |
|---|---|
| Pest | ✅ **235 passed** (703 assertions), chạy trên MySQL |
| Laravel Pint | ✅ PASS |
| Larastan mức 8 | ✅ No errors |
| Deptrac | ✅ 0 violations |
| ESLint / Prettier / `tsc` / `next build` | ✅ PASS |

Mục 1.5 thêm **30 test**: API bình luận (13), nhắc tên (7), đính kèm (10).

Đã **chạy thử thật** qua Nginx bằng một tài khoản tạm: viết bình luận có nhắc tên → người được nhắc được ghi vào `task_comment_mentions`; tải một ảnh PNG thật → lưu với tên ngẫu nhiên và Horizon sinh thumbnail trên hàng đợi `media`; tải một tệp `.jpg` chứa mã PHP → **422** kèm câu tiếng Việt rõ nghĩa. Tài khoản tạm và dữ liệu của nó đã xoá.

#### Quyết định phát sinh khi làm 1.5

**Nhắc tên đồng nghĩa với chia sẻ quyền xem.** Người được nhắc sẽ vào danh sách theo dõi task, tức là mở được task đó dù không thuộc phòng ban liên quan. Đây là chủ ý: kéo đồng nghiệp vào một cuộc trao đổi mà họ không mở được task thì tính năng vô nghĩa. Đổi lại, mọi lần nhắc đều lưu vết trong `task_comment_mentions` nên luôn tra được ai đã kéo ai vào. Nếu công ty muốn siết lại, chỗ sửa là `SyncCommentMentionsAction` — một chỗ duy nhất.

**Chỉ dò dạng `@[Tên hiển thị](uuid)`, không dò `@tên` gõ tay.** Tên người Việt trùng nhau rất nhiều; đoán sai người trong hệ thống giao việc nghĩa là gửi thông báo về một việc không phải của họ. Ô soạn thảo chèn dấu này khi người dùng chọn trong danh sách gợi ý, nên người dùng không bao giờ phải gõ cú pháp đó.

**Nội dung bình luận trả về dạng thô, server không sinh HTML.** Frontend cắt chuỗi thành node React và dựng chip nhắc tên; không có đường nào đi qua `dangerouslySetInnerHTML`. Server sinh HTML từ nội dung người dùng là mở đúng một đường cho XSS lưu trữ.

**Dùng luật `mimetypes` chứ không phải `mimes`.** `mimes` chỉ nhìn phần mở rộng, nên đổi tên `shell.php` thành `anh.jpg` là qua được. `mimetypes` đọc nội dung thật bằng finfo. Đã kiểm chứng bằng cả test lẫn một lượt tải thật.

**Test suýt tự lừa mình.** Bản đầu tôi dùng `UploadedFile::fake()->createWithContent()` để dựng tệp nguỵ trang — nhưng hàm đó gán sẵn kiểu MIME suy từ phần mở rộng, nên test sẽ xanh vì chính con số mình vừa bịa ra chứ không vì mã nguồn đúng. Đã đổi sang ghi nội dung thật ra đĩa và để Symfony tự đoán kiểu, đúng như khi nhận một lượt tải lên thật.

**Tệp bị từ chối ở tầng lưu trữ từng trả 500.** Danh sách trắng kiểm ở hai nơi — Form Request và media collection. Khi tầng thứ hai từ chối, `FileUnacceptableForCollection` không được xử lý và thành lỗi 500. Đây là lỗi của dữ liệu gửi lên, không phải lỗi máy chủ: người dùng cần biết để chọn tệp khác, còn 500 thì họ bấm lại mãi mà không hiểu vì sao. Đã map thành 422 trong `ApiExceptionRenderer`.

**Tên tệp trên đĩa là uuid, tên gốc chỉ giữ để hiển thị.** Giữ tên gốc là mở đường cho traversal, ký tự điều khiển, và trùng tên giữa hai người tải cùng lúc. Phần mở rộng cũng suy từ kiểu MIME thật (`guessExtension()`), không lấy từ chuỗi client gửi lên.

**Không nhận tệp SVG.** SVG chứa được JavaScript và chạy trong ngữ cảnh tên miền của mình khi mở trực tiếp — tức là XSS lưu trữ. Muốn nhận thì phải phục vụ dưới `Content-Type` vô hại, việc đó để [mục 1.9](#19-bảo-mật--đã-xong).

**Thumbnail chạy ở hàng đợi riêng `media`.** Một lượt tải mười ảnh sẽ chiếm hết worker và mọi job khác phải xếp hàng sau nó. Đã thêm `media` vào danh sách hàng đợi của Horizon. Lưu ý vận hành: **đổi cấu hình hàng đợi phải khởi động lại Horizon**, nếu không job nằm im trong hàng mà không có gì báo — tôi gặp đúng chuyện này lúc chạy thử.

**Trả lời chỉ lồng một cấp.** Trả lời vào một câu trả lời sẽ bị kéo về làm con của bình luận gốc. Lồng sâu tuỳ ý thì trên điện thoại thụt lề tới mức không đọc nổi, mà cũng không ai cần tới cấp thứ ba.

**Không ai sửa được lời của người khác, kể cả cấp trên.** Sửa được lời người khác thì cả dòng trao đổi mất giá trị làm bằng chứng — trong hệ thống có thưởng phạt theo tiến độ, đó là chuyện lớn. Quản lý chỉ được **xoá** (xoá mềm, vẫn còn vết) để gỡ nội dung không phù hợp. Bình luận đã sửa luôn hiện nhãn "đã sửa".

**Bình luận và tệp gửi làm hai request.** Tạo bình luận bằng JSON trước, rồi tải tệp lên bình luận vừa tạo bằng multipart. Bình luận không kèm tệp là phần lớn trường hợp, không nên bắt mọi client dựng multipart cho chúng. Nếu bước tải tệp hỏng thì bình luận vẫn còn — người dùng không mất công viết lại.

### 1.6 Deadline & nhắc việc ✅ Đã xong

- [x] Job nền quét task sắp tới hạn (còn dưới 24 giờ) và đã quá hạn
- [x] Lịch chạy trong `routes/console.php`: mỗi giờ, chỉ ngày làm việc, 8h–18h giờ Việt Nam
- [x] Bảng `notifications` (dùng Laravel Notification có sẵn)
- [x] Kênh thông báo trong ứng dụng: chuông đếm số chưa đọc + trung tâm thông báo
- [x] Kênh email cho các sự kiện quan trọng (được giao việc, quá hạn, được nhắc tên)
- [x] `Queue::route()` gom job thông báo vào hàng đợi `notifications` riêng
- [x] Laravel Horizon theo dõi queue — đã cài từ [mục 1.1](#11-khởi-tạo-dự-án--đã-xong)
- [x] Người dùng tự bật/tắt từng loại thông báo, theo từng kênh
- [ ] Thông báo qua **Zalo OA / Telegram** — để đợt 2. Đây mới là kênh nhân viên thực sự đọc, nhưng cần thời gian xin cấu hình phía nhà cung cấp

#### Năm loại thông báo

| Loại | Trong ứng dụng | Email | Vì sao đặt mặc định vậy |
|---|---|---|---|
| Được giao việc | ✅ | ✅ | Bỏ lỡ là việc nằm im tới lúc quá hạn |
| Việc đã quá hạn | ✅ | ✅ | Có hậu quả thật với đánh giá đúng hạn |
| Được nhắc tên | ✅ | ✅ | Lời gọi trực tiếp, có người đang chờ trả lời |
| Việc sắp tới hạn | ✅ | ❌ | Mở app là thấy; email hằng ngày sẽ thành tiếng ồn |
| Có bình luận mới | ✅ | ❌ | Một task sôi nổi sinh vài chục bình luận/ngày |

Người dùng chỉnh lại được từng ô trong *Cài đặt thông báo*. Hộp thư đầy thông báo là hộp thư không ai đọc — và lúc đó thông báo thật sự quan trọng cũng trôi qua.

#### Endpoint đã có

| Nhóm | Đường dẫn |
|---|---|
| Thông báo | `GET /notifications` · `GET /notifications/unread-count` · `PATCH /notifications/{id}/read` · `POST /notifications/read-all` |
| Tuỳ chọn | `GET /notification-settings` · `PATCH /notification-settings` |

#### Kết quả kiểm thử

| Cổng | Kết quả |
|---|---|
| Pest | ✅ **263 passed** (766 assertions), chạy trên MySQL |
| Laravel Pint | ✅ PASS |
| Larastan mức 8 | ✅ No errors |
| Deptrac | ✅ 0 violations |
| ESLint / Prettier / `tsc` / `next build` | ✅ PASS |

Mục 1.6 thêm **28 test**: thông báo theo sự kiện, tuỳ chọn kênh, quét deadline, trung tâm thông báo (21) và chuẩn hoá múi giờ (7).

Đã **chạy thử thật** với hai tài khoản tạm: A giao việc cho B → B nhận thông báo trong chuông và email; B bình luận nhắc tên A → A nhận đúng loại *được nhắc tên* (không phải *có bình luận mới*); job quét deadline sinh cả hai loại nhắc. Email hiển thị đúng tiếng Việt, dấu nhắc `@[Tên](uuid)` được rút gọn thành `@Tên`, nút bấm trỏ về giao diện. Tài khoản tạm và dữ liệu đã xoá.

#### Quyết định phát sinh khi làm 1.6

**Chạy thật phát hiện một lỗi múi giờ lệch 7 tiếng — nghiêm trọng nhất từ đầu dự án.** Tôi đặt hạn 20:00 tối, thông báo hiện "hạn 03:00 sáng hôm sau". Nguyên nhân nằm ở cast `datetime` của Eloquent: nó lưu bằng `Carbon::parse($value)->format('Y-m-d H:i:s')` và **không đổi về UTC**. Hai hệ quả cùng một gốc:

- Client gửi `2026-08-07T20:00:00+07:00` → Carbon hiểu đúng là 13:00 UTC, nhưng `format()` in ra giờ địa phương `20:00` và đúng chuỗi đó rơi vào cột. **Offset bị nuốt mất.**
- Client gửi `2026-08-07T20:00` — đúng thứ ô `<input type="datetime-local">` gửi lên, tức là **mọi hạn nhập từ giao diện tôi làm ở mục 1.7** → Carbon hiểu là 20:00 UTC, tức 03:00 sáng hôm sau giờ Việt Nam.

Loại lỗi này không có gì báo: dữ liệu vẫn lưu, vẫn đọc ra được, chỉ sai giờ. Nó chỉ lộ khi có người đọc một mốc cụ thể và thấy lệch — mà lúc đó bảng chấm công và chỉ số đúng hạn ở các đợt sau đã sai từ lâu. Đã thêm `App\Support\Time\IncomingDateTime` chuẩn hoá tại biên (có offset thì tin offset, không có thì hiểu là giờ Việt Nam, kết quả luôn là UTC), sửa `toDatetimeLocalValue()` phía frontend để dựng chuỗi theo giờ Việt Nam thay vì giờ máy, và khoá lại bằng 7 test.

**Bỏ người thực hiện từng bị chặn.** `AssignTaskController` để `assignee_id` là `required`, trong khi hộp thoại "Giao lại" ở mục 1.7 có lựa chọn *Chưa giao* và gửi `null` — bấm vào là 422. Đổi sang `nullable`, và nhân đó tách `AssignTaskAction` ra khỏi controller: "người bấm nút trở thành người giao việc mới" là luật nghiệp vụ, không phải chuyện điều phối HTTP.

**Mỗi task chỉ được nhắc một lần cho mỗi mốc.** Job chạy mỗi giờ; không đánh dấu thì cùng một task báo chín lần một ngày và người dùng tắt thông báo ngay hôm đầu. Dấu vết ở hai cột `due_soon_notified_at` / `overdue_notified_at`, và **bị xoá khi task được dời hạn** — không xoá thì dời hạn ra xa rồi tới gần sẽ im lặng hoàn toàn, đúng lúc người ta cần nhắc nhất. Task của người đã nghỉ việc cũng được đánh dấu dù không gửi cho ai, nếu không lượt quét sau lại nhặt lên và lặp mãi.

**Được nhắc tên và có bình luận mới là hai loại khác nhau.** Được nhắc đích danh là lời gọi trực tiếp; "có bình luận trên việc mình theo dõi" chỉ là tin nền. Gộp làm một thì người dùng chỉ có lựa chọn nhận cả hai hoặc mất cả hai. Người vừa được nhắc **không** nhận thêm bản "có bình luận mới" dù họ cũng nằm trong danh sách theo dõi — hai thông báo cho cùng một bình luận là thứ khiến người ta tắt hết.

**Không có dòng tuỳ chọn nghĩa là dùng mặc định.** Bảng `user_notification_settings` chỉ ghi dòng cho loại người dùng đã tự chỉnh. Thêm loại thông báo mới ở đợt 2–4 sẽ không cần backfill cho toàn bộ nhân sự, và mặc định vẫn đổi được ở một chỗ duy nhất là enum.

**Chuông hỏi lại mỗi phút, không dùng realtime.** Đợt 1 chưa có WebSocket, và một truy vấn đếm mỗi phút cho vài trăm nhân sự rẻ hơn nhiều so với dựng hạ tầng broadcast chỉ để hiện một con số. Danh sách chỉ nạp khi người dùng thật sự mở chuông.

**Hàng đợi riêng, không chung một.** `auth` → `default` → `notifications` → `media`. Một đợt quét deadline gửi vài trăm email nằm chung `default` thì người bấm "giao việc" phải chờ hết cả đợt. Nguyên tắc xếp thứ tự: **hàng nào có người đang ngồi đợi thì lên trước**, việc nền xuống sau — vì vậy `auth` (mã OTP đăng nhập, xem [mục 1.2b](#12b-xác-thực-hai-lớp-otp--đã-xong)) đứng đầu. Lưu ý vận hành lặp lại từ mục 1.5: **đổi cấu hình hàng đợi phải khởi động lại Horizon**.

**Test suýt xanh vì lý do sai.** Tôi viết `assertSentTo(...)` kèm callback kiểm `via()` trả về mảng rỗng, để chứng minh "tắt hết kênh thì không gửi". Nhưng `NotificationFake` **không ghi lại gì khi `via()` rỗng**, nên callback không bao giờ chạy. Đã đổi sang `assertNotSentTo()` — khẳng định đúng thứ quan sát được — và thêm một test riêng chứng minh tắt một kênh không làm tắt kênh còn lại, vì nếu `via()` cứ trả rỗng bất kể tuỳ chọn thì test đầu vẫn xanh mà tính năng thì hỏng.

**Logo trong email trỏ về giao diện, không về API.** Mặc định của Laravel dùng `config('app.url')` — đúng với ứng dụng một khối, nhưng dự án này tách hai địa chỉ nên bấm vào chỉ ra một trang JSON. Đã thêm `config('app.frontend_url')` và sửa cả chân thư sang tiếng Việt; sửa một chỗ, mọi email của hệ thống được lợi.

**Thông báo lưu dữ liệu thô, không lưu HTML.** Frontend đọc đúng một bộ khoá (`type`, `title`, `message`, `url`, …) cho mọi loại, nên thêm loại mới không phải sửa giao diện.

### 1.7 Giao diện ✅ Đã xong

**Đã làm** (phần phục vụ đăng nhập, làm sớm để kiểm chứng luồng xác thực):

- [x] Hệ màu và token giao diện, sáng/tối, `prefers-reduced-motion`
- [x] Nhận diện Explus: dấu `+`, chữ Be Vietnam Pro (có subset `vietnamese`) + JetBrains Mono
- [x] **Trang đăng nhập 4 bước** — nền aurora nhiều lớp, thẻ kính, ô nhập OTP 6 ký tự tự nhảy ô và nhận dán, nút gửi lại có đếm ngược
- [x] Màn lưu mã khôi phục — có nút chép và tải `.txt`, buộc tích xác nhận trước khi vào app
- [x] Route guard `src/proxy.ts` (Next.js 16 đổi tên Middleware thành **Proxy**)
- [x] **Trang chủ sau đăng nhập** — khung: đầu trang, menu người dùng, đăng xuất, hiện thông tin tài khoản và quyền lấy từ API thật
- [x] Xử lý phiên hết hạn giữa chừng: tự đưa về đăng nhập

Trang chủ cố tình **không vẽ dữ liệu giả**. Một màn hình trông như đã xong mà bấm vào không có gì thì khó hiểu hơn hẳn một khung trống nói rõ đang thiếu gì.

**Màn hình quản lý công việc** — dựng sau khi có API ở [mục 1.4](#14-api-task--đã-xong):

- [x] Layout chung: thanh bên cố định từ `lg`, ngăn kéo trượt trên màn hình hẹp
- [x] **Trang "Hôm nay của tôi"** — bốn nhóm theo hạn: quá hạn / hôm nay / tuần này / xa hơn
- [x] Danh sách task: lọc theo trạng thái, ưu tiên, dự án, quá hạn; tìm theo từ khoá; phân trang
- [x] Bảng Kanban kéo thả theo trạng thái
- [x] Trang chi tiết task: thông tin, dòng thời gian hoạt động, đổi trạng thái, giao lại, dời hạn
- [x] Form tạo task
- [x] Trang dự án, chi tiết dự án, danh sách task theo dự án, quản lý thành viên
- [x] **Responsive mobile-first** — bảng đổi thành thẻ dưới `md`, mọi vùng bấm đủ lớn cho ngón tay
- [x] Manifest PWA, cài lên màn hình chính được
- [x] Trạng thái tải, trạng thái rỗng, xử lý lỗi cho mọi màn hình
- [x] Chuông và trung tâm thông báo — làm ở [mục 1.6](#16-deadline--nhắc-việc--đã-xong)
- [x] Khu vực bình luận trong chi tiết task — làm ở [mục 1.5](#15-bình-luận--trao-đổi--đã-xong)
- [x] **Làm lại toàn bộ hình thức phần đã đăng nhập** — nền trung tính lạnh thay nền be, ba mặt phẳng, bóng đổ hai lớp, icon cho thanh điều hướng, avatar màu theo tên. Bố cục và vị trí các thành phần giữ nguyên nên không phải học lại. Lý do từng lựa chọn ở [Nhận diện & hệ giao diện](#nhận-diện--hệ-giao-diện); nhân đó sửa hai lỗi tương phản có sẵn (lime làm màu chữ, và viền đỏ của nhãn "Trễ hạn" bị lớp cơ sở của `Pill` đè mất)
- [x] **Người dùng tự chọn Sáng / Tối / Theo máy** — token chuyển từ `@media (prefers-color-scheme)` sang `<html data-theme>`, có script chặn nháy trắng chạy trước khung hình đầu tiên. Nhân đó phát hiện biến thể `dark:` của Tailwind vẫn bám vào hệ điều hành, gây lệch khi người dùng chọn ngược với máy — xem [Nhận diện & hệ giao diện](#người-dùng-tự-chọn-sáng--tối-và-một-phím-tắt-cho-cả-app)
- [x] **Bảng lệnh Ctrl+K** — đi tới mọi trang bằng bàn phím, gõ không dấu vẫn ra, dùng chung nguồn quyền với thanh bên
- [x] **Menu tài khoản** thay cho hàng nút phơi ra ở góc phải; hook `usePopover` dùng chung, bổ sung phím Esc mà chuông thông báo đang thiếu
- [ ] Service worker — **chuyển sang** [mục 1.10](#110-vận-hành--đưa-vào-sử-dụng)

#### Bản đồ màn hình

| Đường dẫn | Màn hình |
|---|---|
| `/overview` | Tổng quan toàn công ty — chỉ hiện với người có quyền `task.view.all`, và là đích đến sau khi họ đăng nhập. Xem [Trang Tổng quan](#trang-tổng-quan--đã-xong) |
| `/` | Hôm nay của tôi |
| `/tasks` · `/tasks/new` · `/tasks/{id}` | Danh sách, tạo mới, chi tiết công việc |
| `/board` | Bảng Kanban |
| `/team` | Việc của đội — chỉ hiện với người có quyền `task.view.team` |
| `/projects` · `/projects/{id}` | Danh sách và chi tiết dự án |
| `/notifications` · `/settings/notifications` | Trung tâm thông báo và cài đặt — thêm ở [mục 1.6](#16-deadline--nhắc-việc--đã-xong) |
| `/reports` | Báo cáo ngày — của mình, và của cả phòng với người có `report.view.team`. Xem [đợt 2](#đợt-2--báo-cáo-tiến-độ-hằng-ngày--phần-chữ-đã-xong) |
| `/attendance` | Chấm công — giờ làm của mình, và bảng công cả phòng với người có quyền. Xem [đợt 3](#đợt-3--chấm-công--phần-đo-đã-xong) |
| `/payroll` | Lương — mức của mình, và bảng lương công ty với người có `payroll.view.all`. Xem [Mức lương](#mức-lương--phần-đặt-và-xem-đã-xong) |
| `/bonus` | Thưởng dự án — phần của mình, và quỹ theo dự án với người có `bonus.view.all`. Xem [Thưởng dự án](#thưởng-dự-án--quỹ-và-chia-thủ-công-đã-xong) |
| `/employees` | Quản trị nhân sự — xem [Quản trị nhân sự](#quản-trị-nhân-sự--đã-xong) |
| `/login` | Đăng nhập 4 bước — nằm ngoài nhóm route `(app)` nên không có thanh bên |

#### Kết quả kiểm thử

| Cổng | Kết quả |
|---|---|
| ESLint | ✅ PASS |
| Prettier | ✅ PASS |
| `tsc --noEmit` | ✅ No errors |
| `next build` | ✅ 11 route dựng xong |
| Pest (backend, sau khi thêm 2 endpoint) | ✅ **205 passed** (629 assertions) |

Đã **chạy thử thật**, không chỉ qua kiểm tra tĩnh: đăng nhập đủ hai bước bằng tài khoản tạm, gọi lần lượt `/tasks/my`, `/tasks`, `/tasks/team`, `/projects`, `/users/assignable`, đổi trạng thái một task rồi đọc lại nhật ký. Mọi đường dẫn giao diện trả 200, và `/tasks` khi chưa đăng nhập trả 307 về `/login?redirect=%2Ftasks` đúng như thiết kế. Tài khoản tạm đã xoá sau khi kiểm tra xong.

#### Quyết định phát sinh khi làm 1.7

**Hai endpoint còn thiếu, lộ ra khi dựng giao diện chứ không phải khi đọc lại kế hoạch.**

- `GET /tasks/{id}/activities` — mục 1.4 có ghi nhật ký nhưng không có đường đọc ra, nên trang chi tiết không vẽ được dòng thời gian.
- `GET /users/assignable` — đây là lỗ hổng thật: `GET /users` đòi quyền `user.manage`, mà **trưởng phòng có quyền giao việc nhưng không có quyền quản trị nhân sự**. Dùng chung một đường thì họ không mở nổi ô chọn người thực hiện. Endpoint mới trả về đúng bốn trường cần để vẽ ô chọn — không kèm vai trò, quyền hay số điện thoại. Danh bạ nội bộ thì ai cũng xem được, hồ sơ nhân sự thì không.

**Nhật ký ghi ngày sai định dạng — chỉ phát hiện khi gọi thật.** Test cũ chỉ kiểm `status`, nên không ai thấy `started_at` được ghi thành `"2026-08-07 04:13:38"`: chuỗi trần không kèm múi giờ. Mảng thuộc tính của Eloquent giữ ngày ở dạng đó, nên nhánh `DateTimeInterface` trong Observer không bao giờ chạy. Trình duyệt đọc chuỗi ấy thành giờ máy — **lệch bảy tiếng với người dùng ở Việt Nam, mà không có gì báo là đã lệch**. Đã sửa Observer đọc qua `getAttribute()` / `getOriginal($key)` để cast được áp dụng, và thêm test khoá đúng định dạng ISO 8601 kèm offset.

**Kéo thả không được là cách duy nhất để đổi trạng thái.** Mỗi thẻ Kanban có thêm ô "Chuyển sang…". Kéo trên màn hình cảm ứng nhỏ rất khó trúng, và người dùng bàn phím hoặc trình đọc màn hình cần một đường đi thẳng. Kéo thả là lối tắt cho chuột, không phải điều kiện để dùng được tính năng. Thư viện dùng `@dnd-kit/core` (hỗ trợ chuột, cảm ứng và bàn phím); thông báo cho trình đọc màn hình đã dịch sang tiếng Việt vì mặc định của thư viện là tiếng Anh.

**Luồng chuyển trạng thái kiểm ở cả hai đầu.** Frontend giữ một bản sao `ALLOWED_TRANSITIONS` để không hiện những lựa chọn chắc chắn bị từ chối. Đây là chuyện trải nghiệm, không phải bảo mật — luật thật vẫn nằm ở `TaskStatus::canTransitionTo()` và chặn dù client gửi gì.

**Bộ lọc công việc nằm trong địa chỉ trang, bộ lọc dự án thì không.** Danh sách công việc lưu bộ lọc vào query string: tải lại trang không mất lọc, nút Back quay đúng chỗ, và gửi được cho đồng nghiệp đường dẫn "việc quá hạn của phòng tôi". Dự án chỉ vài chục cái và không ai gửi nhau đường dẫn "dự án đang chạy", nên giữ trong state cho gọn. Không phải chỗ nào cũng cần đến mức đó.

**`components/ui` không được biết task có những trạng thái nào.** Luật `no-restricted-imports` từ mục 1.1 bắt được ngay khi tôi đặt `StatusBadge` vào đó. Đã tách: `components/ui/pill.tsx` là viên nhãn thuần giao diện, còn nhãn mang nghiệp vụ nằm ở `features/tasks/components/task-badges.tsx` và `features/projects/components/project-badges.tsx`. Không tách thì mỗi lần thêm một trạng thái lại phải sửa vào tầng dùng chung của cả ứng dụng.

**Nhãn trạng thái luôn có chữ, màu chỉ là thông tin phụ.** Khoảng 8% nam giới bị mù màu đỏ–lục; "đang làm" với "đã huỷ" mà chỉ khác nhau ở màu thì họ không phân biệt được.

**Không dùng `useEffect` để đồng bộ state theo prop.** ESLint có luật `react-hooks/set-state-in-effect` và nó đúng ở cả hai chỗ tôi vi phạm: ngăn kéo giờ đóng ngay trong `onClick` của mục điều hướng, còn ô tìm kiếm chỉnh state ngay trong thân render theo cách React khuyến nghị. Effect chạy sau khi đã vẽ xong, nên cách cũ để lại một khung hình hiện giá trị cũ.

**Bảng Kanban chặn cứng 100 task và nói rõ khi chạm trần.** Kéo thả giữa hai trang là vô nghĩa nên bảng không phân trang được. Im lặng cắt bớt thì người dùng đọc thành "phòng mình chỉ có ngần này việc".

**Chưa làm service worker.** Manifest đã có nên cài lên màn hình chính và chạy toàn màn hình được. Nhưng một service worker cache sai trên ứng dụng dữ liệu sống là loại lỗi tệ nhất: nhân viên thấy danh sách việc của hôm kia, tưởng đã xong hết, và không tự sửa được ngoài cách xoá dữ liệu trình duyệt. Để mục 1.10 làm cùng lúc với chiến lược cache và cơ chế cập nhật.

**`TaskFilters` khai báo bằng `type`, không bằng `interface`.** Chỉ kiểu dạng `type` mới có chỉ mục ngầm, mà `apiFetch` nhận tham số `query` kiểu `Record<string, …>`. Dùng `interface` thì TypeScript từ chối ngay chỗ truyền vào.

**Khoá chính của `task_activities` được lộ ra API.** Trái với quy ước "chỉ lộ uuid", nhưng bảng này không có cột uuid, chỉ đọc được qua đúng một task mà người dùng đã thấy được, và không có đường `/activities/{id}` để dò tuần tự — nên lộ khoá ở đây không mở ra thứ gì.

**Cảnh báo bảo mật có sẵn trong `npm audit`.** Hai lỗi mức cao đến từ `js-yaml` trong `@redocly/openapi-core`, là phụ thuộc gián tiếp của `openapi-typescript` — gói chỉ dùng cho lệnh `npm run api:types` ở môi trường phát triển, không đi vào bản build. Đã có từ trước mục 1.7, không phải do `@dnd-kit`. **Đã xử lý ở [mục 1.9](#19-bảo-mật--đã-xong)** bằng `overrides` trong package.json.

### 1.8 Kiểm thử & triển khai ✅ Đã xong

- [x] Feature test cho toàn bộ endpoint task và comment
- [x] Test phân quyền: nhân viên không xem/sửa được task ngoài phạm vi
- [x] Test luồng chuyển trạng thái hợp lệ và không hợp lệ
- [x] Test job nhắc deadline — làm ở [mục 1.6](#16-deadline--nhắc-việc--đã-xong)
- [x] **Test E2E luồng chính** — đăng nhập thật (email + mật khẩu + OTP) → tạo → giao → bình luận → hoàn thành
- [x] Test múi giờ: tạo task lúc 23h30 giờ VN, kiểm tra hạn và ngày không lệch
- [x] **Tài liệu API** — OpenAPI 3.1 sinh tự động bằng Scramble, 35 đường dẫn
- [x] Hướng dẫn sử dụng cho nhân viên — [`docs/huong-dan-su-dung.md`](docs/huong-dan-su-dung.md)
- [ ] Test trình duyệt thật (Playwright) — **chuyển sang** [mục 1.10](#110-vận-hành--đưa-vào-sử-dụng), cần môi trường staging

#### Tài liệu API

```bash
# Sinh lại spec sau khi đổi endpoint
docker compose exec app php artisan scramble:export

# Sinh kiểu TypeScript cho frontend từ chính spec đó
cd frontend && npm run api:types
```

| Đường dẫn | Nội dung |
|---|---|
| `/docs/api` | Giao diện đọc tài liệu |
| `/docs/api.json` | Spec OpenAPI 3.1 |
| `backend/storage/api-docs/openapi.json` | Bản xuất ra tệp, nguồn cho `npm run api:types` |

Tài liệu **không công khai**: `Gate::viewApiDocs` chỉ mở ở môi trường local hoặc cho người có quyền `role.manage`. Danh sách đầy đủ endpoint và tham số là tấm bản đồ cho người muốn dò tìm.

#### Kết quả kiểm thử

| Cổng | Kết quả |
|---|---|
| Pest | ✅ **308 passed** (874 assertions), chạy trên MySQL |
| Laravel Pint | ✅ PASS |
| Larastan mức 8 | ✅ No errors |
| Deptrac | ✅ 0 violations |
| `composer audit` | ✅ No advisories |
| `npm audit` | ✅ 0 vulnerabilities |
| ESLint / Prettier / `tsc` / `next build` | ✅ PASS |

Mục 1.8 và 1.9 thêm **45 test**: E2E và múi giờ (5), IDOR (18), siết bảo mật (22).

#### Quyết định phát sinh khi làm 1.8

**Test E2E không dùng `actingAs()`.** Đó là điểm khác biệt duy nhất nhưng quan trọng: `actingAs()` bỏ qua middleware xác thực, nên mọi test khác vẫn xanh kể cả khi luồng đăng nhập hỏng. Ba lỗi thật đã gặp ở dự án này — vòng lặp chuyển hướng, CORS chặn trình duyệt, cờ phiên sai — đều thuộc loại đó. Test E2E đăng nhập bằng đúng đường người dùng đi: email, mật khẩu, rồi mã OTP đọc từ hộp thư.

**Mỗi test E2E chỉ đăng nhập một lần, và đó là giới hạn của bộ test chứ không phải của ứng dụng.** Bản đầu tôi viết một test đi hết luồng có đổi vai giữa chừng: sếp → đăng xuất → nhân viên → đăng xuất → sếp. Nó đỏ. Đào ra thì bộ test dùng session driver `array`, và driver đó không mô phỏng được vòng đời cookie — sau `logout`, lần đăng nhập thứ hai trong cùng một test luôn trả 401. Tệ hơn: `/auth/me` **vẫn trả 200 ngay sau khi đăng xuất** trong bộ test.

Con số thứ hai đủ đáng ngờ để phải kiểm bằng tay. Chạy curl qua Nginx: `/auth/me` trả **200 trước khi đăng xuất và 401 sau đó** — ứng dụng đúng, bộ test nói dối. Đã tách thành nhiều test, mỗi test một lần đăng nhập, và ghi rõ giới hạn này ngay trong file để người sau không mất công đào lại.

**Tài liệu API sinh tự động, không viết tay.** 35 endpoint viết tay là 35 chỗ sẽ lỗi thời ngay lần sửa đầu tiên. Scramble đọc thẳng Form Request, Resource và route để dựng spec, nên tài liệu sai chỉ khi mã nguồn sai. Cùng tệp đó nuôi luôn `npm run api:types` — frontend đổi field mà backend không đổi là lỗi lúc build, không phải lúc chạy.

### 1.9 Bảo mật ✅ Đã xong

Hệ thống này lưu **dữ liệu nhân sự và về sau là dữ liệu lương**. Đây không phải phần làm cho có.

**Xác thực & phiên làm việc**

- [x] Rate limit đăng nhập, khoá tạm 5 phút sau 5 lần sai — làm ở [mục 1.2](#12-xác-thực--phân-quyền)
- [x] **Chính sách mật khẩu** — tối thiểu 12 ký tự, có chữ và số, và đối chiếu kho mật khẩu đã lộ qua `uncompromised()`
- [x] **Xác thực 2 lớp bắt buộc với TOÀN BỘ nhân viên** — xem [mục 1.2b](#12b-xác-thực-hai-lớp-otp--đã-xong)
- [x] Token có hạn (12 giờ) và phiên tự hết sau 120 phút không hoạt động
- [x] **Thu hồi toàn bộ token ngay khi tài khoản bị vô hiệu hoá** — không chờ token tự hết hạn
- [x] Nhật ký đăng nhập: thời điểm, IP, thiết bị, thành công/thất bại — bảng `login_attempts`

**Phân quyền**

- [x] **Test IDOR cho mọi endpoint** — 18 test, đổi uuid trong URL sang bản ghi của người khác đều trả 403/404
- [x] Policy kiểm tra theo từng bản ghi, không chỉ theo vai trò — `TaskPolicy`, `ProjectPolicy`, `TaskCommentPolicy`, `UserPolicy`
- [x] Kiểm tra quyền cả ở endpoint danh sách, không riêng endpoint chi tiết — scope `visibleTo` ở [mục 1.4](#14-api-task--đã-xong)
- [x] **Rate limit cho các endpoint ghi** — 60/phút cho ghi, 300/phút cho đọc, hai bộ đếm tách riêng

**Tải tệp lên**

- [x] Danh sách trắng MIME type, **kiểm tra nội dung thật chứ không tin phần mở rộng** — luật `mimetypes` ở [mục 1.5](#15-bình-luận--trao-đổi--đã-xong)
- [x] Đổi tên file khi lưu, không giữ tên gốc người dùng đặt — tên trên đĩa là uuid, phần mở rộng suy từ nội dung
- [x] **Chặn SVG** — không có trong danh sách trắng
- [x] Giới hạn dung lượng và số lượng file mỗi request — 10 MB, 5 tệp
- [x] **Tệp đính kèm luôn tải xuống, không mở trong tab** — `Content-Disposition: attachment` + `nosniff` ở Nginx
- [x] File không nằm trong thư mục web, chỉ truy cập qua presigned URL có hạn — **đã làm phần phát URL** (`App\Support\Media\MediaUrl`, hạn 30 phút, 5 test). Còn hiệu lực khi đổi `MEDIA_DISK=r2`; ở đĩa `public` của máy dev thì vẫn là đường dẫn thẳng, có chủ ý. Xem [Đường dẫn tệp đính kèm và Cloudflare R2](#đường-dẫn-tệp-đính-kèm-và-cloudflare-r2)

**Tầng ứng dụng**

- [x] CORS chỉ cho phép đúng origin của frontend, không dùng `*` — `config/cors.php` từ [mục 1.2](#12-xác-thực--phân-quyền)
- [x] Bảo vệ CSRF — Sanctum SPA dùng cookie `XSRF-TOKEN`, mọi request ghi phải gửi lại ở header
- [x] **Header bảo mật** — `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, CSP, `Permissions-Policy`, và HSTS khi chạy HTTPS
- [x] **Không để lộ bí mật khi serialize** — `two_factor_secret` và mã khôi phục vào danh sách ẩn của model
- [x] Không ghi dữ liệu cá nhân hay token vào log — mã nguồn không gọi `Log::` ở đâu, và `dontFlash()` chặn mật khẩu, mã OTP, mã khôi phục khỏi báo cáo lỗi
- [x] **Rà soát phụ thuộc trong CI** — `composer audit` và `npm audit --audit-level=high`, cả hai đều sạch
- [x] Chặn giao diện Horizon và tài liệu API trên môi trường ngoài local — chỉ người có quyền `role.manage`
- [x] Bảo vệ cột lương — **đã chốt hướng, xem [Vì sao cột lương không mã hoá ở tầng ứng dụng](#vì-sao-cột-lương-không-mã-hoá-ở-tầng-ứng-dụng)**. Tóm tắt: giữ `DECIMAL(15,2)`, mã hoá ở **tầng lưu trữ** khi triển khai (việc của [mục 1.10](#110-vận-hành--đưa-vào-sử-dụng)), và chấp nhận có ghi rõ một rủi ro còn lại

**Tuân thủ**

- [ ] Rà soát theo **Nghị định 13/2023/NĐ-CP về bảo vệ dữ liệu cá nhân** — chuyển sang [mục 1.10](#110-vận-hành--đưa-vào-sử-dụng). Đây là việc pháp lý cần người có thẩm quyền của công ty ký, không phải việc kỹ thuật thuần
- [ ] Xác định thời hạn lưu trữ và quy trình xoá dữ liệu người đã nghỉ việc — cùng lý do

#### Bảng tổng hợp lớp phòng thủ

| Lớp | Đang có gì |
|---|---|
| Mật khẩu | ≥12 ký tự, chữ + số, từ chối mật khẩu đã lộ |
| Đăng nhập | OTP bắt buộc, khoá 5 phút sau 5 lần sai, ghi nhật ký IP và thiết bị |
| Phiên | Cookie httpOnly, hết sau 120 phút không hoạt động, thu hồi ngay khi vô hiệu hoá tài khoản |
| Token API | Hết hạn sau 12 giờ |
| Tần suất | 300 lượt đọc / 60 lượt ghi mỗi phút, đếm theo người dùng |
| Phân quyền | Policy theo từng bản ghi + scope `visibleTo` ở mọi truy vấn danh sách |
| Tải tệp | Danh sách trắng theo nội dung thật, đổi tên, 10 MB, luôn tải xuống |
| Header | nosniff, DENY, no-referrer, CSP `default-src 'none'`, HSTS khi có HTTPS |
| Phụ thuộc | `composer audit` + `npm audit` chặn merge trong CI |

#### Quyết định phát sinh khi làm 1.9

**Hai bí mật xác thực hai lớp không nằm trong danh sách ẩn.** `two_factor_secret` cho phép sinh mã OTP hợp lệ, `two_factor_recovery_codes` cho phép bỏ qua hẳn lớp thứ hai. Cả hai đã mã hoá ở database, nhưng `toArray()` trả về bản **đã giải mã** — và `toArray()` gọi được từ bất kỳ đâu: một `dd()` lúc gỡ lỗi, một payload job đưa vào Redis, một dòng log của thư viện bên thứ ba. `UserResource` không trả chúng ra, nhưng đó chỉ là một chỗ. Đã khai ở model để chặn tại nguồn.

**Header bảo mật từng bị đặt ở hai nơi và mâu thuẫn nhau.** Nginx đã gắn sẵn ba header từ mục 1.1; tôi thêm middleware gắn lại. `add_header` của Nginx **thêm vào** chứ không ghi đè header từ upstream, nên phản hồi ra có hai dòng `X-Content-Type-Options` và hai giá trị `Referrer-Policy` khác nhau — trình duyệt lấy giá trị cuối, tức cấu hình nào thắng là chuyện may rủi. Đã chia lại rõ ràng: **ứng dụng làm chủ phản hồi của mình**, Nginx chỉ lo tệp tĩnh dưới `/storage` mà nó phục vụ thẳng không qua PHP. Phát hiện được vì kiểm bằng `curl -D -` chứ không chỉ chạy test.

**Tệp đính kèm giờ luôn tải xuống.** Thêm `Content-Disposition: attachment` cho `/storage/`. Đây là lớp chặn thứ hai sau danh sách trắng MIME: kể cả khi lọt một tệp có nội dung HTML, trình duyệt cũng không dựng nó thành trang trên tên miền của mình.

**Một limiter, hai hạn mức.** Thay vì hai limiter tên khác nhau rồi phải gắn đúng cho từng route, dùng một limiter `api` tự chọn hạn mức theo phương thức HTTP. Gắn một dòng ở `bootstrap/app.php` là phủ toàn bộ API — không route nào sót, kể cả route thêm sau này. Hai tiền tố khoá khác nhau để bộ đếm đọc và ghi không ăn vào nhau.

**Test rate limit ban đầu không kiểm gì cả.** Tôi bơm sẵn bộ đếm bằng khoá `write|{id}` rồi mong request bị chặn — nhưng `ThrottleRequests` băm lại thành `md5(tên_limiter . khoá)` trước khi đếm. Bơm nhầm khoá nghĩa là bộ đếm thật vẫn trống, request vẫn qua. Test đỏ đúng lúc, và đã sửa thành một hàm phụ trợ có tên rõ ràng kèm chú thích để người sau không mắc lại.

**`uncompromised()` tắt trong môi trường test.** Luật này hỏi API của Have I Been Pwned. Bật trong test là test rung: mất mạng thì cả bộ test đỏ vì lý do không liên quan. Cách hỏi dùng **k-anonymity** — chỉ năm ký tự đầu của mã băm SHA-1 rời khỏi máy chủ, không bao giờ gửi cả mật khẩu lẫn mã băm đầy đủ. Đây là điểm phải nói rõ khi rà soát Nghị định 13.

**Phiên rút từ 480 xuống 120 phút không hoạt động.** Tám tiếng là cả ngày làm việc — một máy bỏ quên ở phòng họp mở toang dữ liệu nhân sự suốt thời gian đó. Hai tiếng đủ để đi ăn trưa mà không phải đăng nhập lại.

**Token Sanctum mặc định sống vĩnh viễn.** Giao diện web không dùng token (nó xác thực bằng cookie phiên), nhưng token cấp cho tích hợp về sau — app di động, script đồng bộ — mà rò ra ngoài thì là lỗ hổng không có thời hạn đóng. Đã đặt 12 giờ.

**`npm audit` đang đỏ và CI đang chặn merge mà chưa ai để ý.** Hai lỗ hổng mức cao ở `js-yaml` (tiêu tốn CPU bậc hai khi phân tích `!!omap`), đi vào qua `openapi-typescript` → `@redocly/openapi-core`, mà bản mới nhất của `openapi-typescript` vẫn ghim bản cũ. Đã ép nâng bằng `overrides` trong `package.json`; giờ cả hai lệnh audit đều sạch. Ghi kèm chú thích để bỏ dòng đó khi thượng nguồn tự nâng.

**Rà soát Nghị định 13 để lại mục 1.10, có chủ ý.** Phần kỹ thuật đã làm được thì đã làm: mã hoá dữ liệu nhạy cảm, hạn chế truy cập, ghi nhật ký, thu hồi quyền khi nghỉ việc. Nhưng bản rà soát tuân thủ cần công ty xác định **mục đích xử lý dữ liệu, thời hạn lưu trữ và người chịu trách nhiệm** — đó là quyết định của lãnh đạo, không phải thứ lập trình viên tự chọn rồi ghi vào mã nguồn.

### Quản trị nhân sự ✅ Đã xong

Bổ sung sau đợt 1. Backend đã có `POST /users` từ [mục 1.2](#12-xác-thực--phân-quyền), nhưng thiếu hẳn giao diện và thiếu một mảnh quan trọng ở chính API.

- [x] **Thêm nhân viên** — form đầy đủ: họ tên, email, mã nhân viên, vai trò, phòng ban, chức vụ, quản lý trực tiếp, ngày vào làm
- [x] **Trả về mật khẩu tạm ngay khi tạo**, hiện đúng một lần kèm nút chép
- [x] Danh sách nhân sự — tìm theo tên/email/mã, lọc theo phòng ban và vai trò
- [x] Mặc định chỉ hiện người đang làm việc, bật riêng để xem cả người đã nghỉ
- [x] Vô hiệu hoá tài khoản, đặt lại mật khẩu, gỡ xác thực hai lớp
- [x] `GET /departments` và `GET /positions` cho ô chọn trong form
- [x] **Sửa hồ sơ nhân viên đã tạo** — đổi phòng ban, chức vụ, quản lý trực tiếp, vai trò, và mọi thông tin liên hệ. Xem [mục riêng bên dưới](#sửa-hồ-sơ-kích-hoạt-lại-và-nhật-ký-nhân-sự)
- [x] **Kích hoạt lại người đã vô hiệu hoá** — trước đây vô hiệu hoá là thao tác một chiều
- [x] **Nhật ký nhân sự** — ai đổi gì của ai, lúc nào
- [ ] **Nhập nhiều nhân viên từ CSV qua giao diện** — lệnh `php artisan users:import` đã có từ [mục 1.3](#13-mô-hình-dữ-liệu--đã-xong), nhưng chỉ chạy được ở dòng lệnh

#### Endpoint đã có

| Nhóm | Đường dẫn |
|---|---|
| Nhân sự | `GET /users` (tìm, lọc phòng ban, lọc vai trò, gồm người đã nghỉ) · `POST /users` · `PUT /users/{id}` |
| Thao tác | `POST /users/{id}/deactivate` · `/activate` · `/reset-password` · `/reset-two-factor` |
| Nhật ký | `GET /users/{id}/activities` |
| Cơ cấu tổ chức | `GET /departments` · `GET /positions` |

Màn hình ở `/employees`, chỉ hiện trong thanh điều hướng với người có quyền `user.manage`.

#### Kết quả kiểm thử

| Cổng | Kết quả |
|---|---|
| Pest | ✅ **328 passed** (3515 assertions) |
| Pint · Larastan mức 8 · Deptrac | ✅ Sạch |
| ESLint / Prettier / `tsc` / `next build` | ✅ PASS |

Thêm **20 test**: luồng tạo và lọc nhân sự (15), sinh mật khẩu tạm (5).

### Trang Tổng quan ✅ Đã xong

#### Bấm vào con số để mở đúng danh sách đó

Bảy ô số đều bấm được, và mỗi ô mở trang Công việc đã lọc sẵn:

| Ô | Đi tới |
|---|---|
| Việc đang mở | `/tasks?open=1` |
| Quá hạn | `/tasks?overdue=1` |
| Hạn hôm nay | `/tasks?due_today=1` |
| Chưa giao ai | `/tasks?unassigned=1` |
| Xong tuần này | `/tasks?completed_this_week=1` |
| Dự án đang chạy | `/projects` |
| Nhân sự đang làm | `/employees` |

Hàng trong bảng *Ai đang làm việc gì* mở `/tasks?assignee_id=…&open=1`; hàng dự án mở trang chi tiết dự án.

**Ô có số 0 thì không bấm được.** Bấm vào "0 việc quá hạn" chỉ dẫn tới danh sách rỗng — một cú bấm bị phí, và tệ hơn là một liên kết nói dối rằng có gì đó để xem.

##### Con số và danh sách dùng CHUNG một định nghĩa

Bốn phép lọc mới (`open`, `unassigned`, `due_today`, `completed_this_week`) là **scope trên model `Task`**, không phải truy vấn viết trong controller. Trang Tổng quan đếm bằng chính scope đó, trang Công việc lọc bằng chính scope đó.

Mỗi bên tự viết truy vấn riêng thì sớm muộn lệch nhau — và lúc ô ghi "12 việc quá hạn" mà danh sách ra 9 dòng, người dùng mất niềm tin vào **cả hai** con số chứ không riêng con số sai. Có 6 test khoá lại: cùng một tập dữ liệu, `summary.X` phải bằng `meta.total` của endpoint danh sách.

##### Một lỗi múi giờ tìm ra khi làm phần này

Trang Tổng quan tính *Hạn hôm nay* bằng `now()->endOfDay()` và *Xong tuần này* bằng `startOfWeek()`. Ứng dụng chạy **UTC** theo quy ước dữ liệu, nên cả hai là ranh giới ngày/tuần theo UTC — **lệch bảy tiếng**.

Cụ thể: lúc 8 giờ sáng giờ Việt Nam, khoảng "hôm nay" kéo tới 06:59 sáng *hôm sau*, nên việc tới hạn rạng sáng mai bị đếm vào hôm nay. Con số vẫn ra một giá trị hợp lý nên không ai nhận thấy — đúng loại lỗi mà `App\Support\Time\WorkDate` sinh ra để chặn.

Lỗi này **chỉ lộ ra vì phải làm cho con số khớp với danh sách**. Còn để nguyên hai chỗ tính riêng thì nó nằm im mãi.

##### Bộ lọc đến từ Tổng quan phải hiện ra và gỡ được

Bốn cờ mới không có ô nhập nào trên thanh lọc — chúng chỉ được đặt bằng cách bấm từ Tổng quan. Không hiện ra thì người dùng thấy một danh sách bị cắt bớt mà **không có gì giải thích vì sao**, và cách duy nhất để thoát là sửa địa chỉ trang bằng tay. Nên mỗi cờ đang bật hiện thành một chip có nút gỡ ngay tại chỗ.



Quản trị viên và giám đốc đăng nhập vào là rơi thẳng vào "Hôm nay của tôi" — màn lọc theo `assignee_id` của chính họ. Mà **không ai giao việc cho giám đốc**, nên thứ họ thấy mỗi sáng là đúng một dòng: *"Bạn không còn việc nào đang mở."* Đúng về mặt kỹ thuật, vô dụng về mặt sử dụng. Kiểm bằng dữ liệu thật: cả hai tài khoản admin đều có 0 việc được giao.

Màn `/overview` trả lời hai câu hỏi mà trước đây không màn nào trả lời được: *đang có dự án nào, tiến độ tới đâu* và *ai đang làm việc gì, ai đang quá tải*.

- [x] Bảy con số chính: việc đang mở, quá hạn, hạn hôm nay, **chưa giao ai**, xong tuần này, dự án đang chạy, nhân sự đang làm
- [x] **Ai đang làm việc gì** — thanh đo chồng hai đoạn: phần đỏ là việc đã trễ nằm *trong* tổng việc đang mở
- [x] **Dự án và tiến độ** — thanh tiến độ xong/tổng kèm số việc trễ, xếp dự án nhiều việc trễ lên đầu
- [x] **Việc trễ lâu nhất** — cũ nhất lên đầu, kèm số ngày trễ
- [x] Người có `task.view.all` được đưa thẳng tới đây sau khi đăng nhập
- [x] Mục "Tổng quan" đứng đầu thanh điều hướng, chỉ hiện với người có quyền

**Không dùng thư viện biểu đồ.** Với quy mô một công ty vài trăm người, biểu đồ tròn chia sáu trạng thái task không giúp ai quyết định điều gì. Thứ có ích là bảng có thanh đo ngay trong dòng — nhìn một giây là thấy ai lệch tải. Thanh đo dựng bằng Tailwind, không thêm KB nào vào gói. Cùng lý do đã bỏ `clsx` và bỏ thư viện icon.

**"Chưa giao ai" là con số đáng giá nhất trên màn này.** Việc không có người làm là thứ dễ trôi nhất trong cả hệ thống: không ai nhận thông báo nhắc hạn (mục 1.6 gửi cho `assignee`), và nó không xuất hiện trong "việc của tôi" của bất kỳ ai. Không có ô này thì chỉ phát hiện ra khi khách hàng hỏi.

#### Vì sao là endpoint gom sẵn, không tính ở frontend

`/tasks/team` phân trang 25 dòng. Cộng dồn từ đó ra con số "toàn công ty có bao nhiêu việc trễ" sẽ luôn sai — và sai theo kiểu tệ nhất: vẫn hiện một con số trông hợp lý, không ai nhận ra. `GET /dashboard/overview` tính bằng `COUNT`/`GROUP BY` chạy thẳng trên database.

**Số truy vấn cố định là 13, không phụ thuộc số nhân sự hay số dự án** — và có test khoá lại điều đó. Đây đúng là loại màn hình dễ thành N+1 nhất ("với mỗi người, đếm việc của họ" → hai trăm nhân sự là hai trăm truy vấn). Test chạy hai lần với lượng dữ liệu chênh nhau mười lần và so số truy vấn.

Bảng tải việc gom bằng `GROUP BY assignee_id` ở phía Task, **không** thêm quan hệ `assignedTasks` vào model `User`: Identity là shared kernel, mọi miền được tham chiếu tới nó nhưng nó không được tham chiếu ngược lên Task. Deptrac sẽ chặn, và luật đó đúng.

#### Ba chi tiết nhỏ nhưng quyết định màn hình có dùng được không

**Dự án chưa có việc nào thì tiến độ là 0, không phải 100.** Chia cho 0 ở đây sẽ hiện "hoàn thành" cho một dự án vừa mở — sai theo hướng nguy hiểm nhất.

**Ô số 0 về tông trung tính.** Một ô đỏ ghi "0 quá hạn" là báo động giả; màu ở đây phải mang nghĩa "cần để ý", không phải "đây là loại số gì".

**Bảng cắt ở 12 dòng nhưng luôn báo tổng.** Hiện 12 dòng trông như toàn bộ công ty trong khi còn ba người nữa đang ôm việc trễ là kiểu nói dối khó chịu nhất. Phản hồi trả cả `rows` lẫn `total`, giao diện hiện "12 trên 15 người".

#### Trưởng phòng bị chặn, có chủ ý

`task.view.team` cho phép xem việc của phòng mình, nhưng một trang **tổng quan công ty** lọc theo phòng sẽ là màn hình khác, mang ý nghĩa khác — "phòng tôi có bao nhiêu việc trễ" không đọc chung được với "công ty có bao nhiêu". Endpoint đòi `task.view.all`. Trưởng phòng vẫn có `/team` như cũ.

#### Kết quả kiểm thử

| Cổng | Kết quả |
|---|---|
| Pest | ✅ **378 passed** (3674 assertions) |
| Pint · Larastan mức 8 · Deptrac | ✅ Sạch |
| ESLint / Prettier / `tsc` / `next build` | ✅ PASS |

Thêm **16 test** trong `tests/Feature/Http/Dashboard/OverviewTest.php`, gồm một test khoá số truy vấn.

Đã **chạy thật qua HTTP** bằng token Sanctum trên dữ liệu demo: `200` trong 0,28 giây với số liệu đúng, `403` cho nhân viên thường, `401` khi chưa đăng nhập. Token kiểm thử đã xoá.

#### Quyết định phát sinh

**Test đếm truy vấn ban đầu đo nhầm thứ khác.** Nó so hai lần chạy và ra 18 với 16 — số truy vấn *giảm* khi dữ liệu tăng gấp mười. Không phải N+1, mà là **đệm quyền của spatie**: lần đo đầu tốn thêm hai truy vấn nạp vai trò và quyền, lần sau chạy trên đệm còn ấm. Nếu chỉ nới lỏng điều kiện cho test xanh thì nó sẽ không còn phát hiện được N+1 thật. Đã thêm `forgetCachedPermissions()` trước mỗi lần đo, và giờ hai lần bằng nhau đúng 13. Dump ra từng câu SQL mới thấy được nguyên nhân — đoán thì không.

### Sửa hồ sơ, kích hoạt lại, và nhật ký nhân sự

Làm sau, khi rà lại thì thấy phần quản trị nhân sự đang hở đúng ba chỗ, và cả ba đều dẫn tới cùng một kết cục: **phải sửa thẳng database**.

| Trước | Sau |
|---|---|
| Không có endpoint sửa. Đổi phòng ban phải `UPDATE users SET department_id = …` | `PUT /users/{id}` |
| Vô hiệu hoá là một chiều. Bấm nhầm, hoặc người nghỉ quay lại làm, là bí | `POST /users/{id}/activate` |
| Không có vết ai đổi vai trò của ai | Bảng `user_activities` + `GET /users/{id}/activities` |

**Xoá mềm: có hạ tầng nhưng cố ý không dùng.** `User` có trait `SoftDeletes` và cột `deleted_at` từ mục 1.3, nhưng không route nào gọi `delete()`. Thứ thực sự chạy là *vô hiệu hoá* (`is_active = false`, ghi `terminated_at`, thu hồi mọi token ngay). Đây là lựa chọn đúng và giữ nguyên: task họ từng làm, bình luận họ từng viết và bảng công của họ phải còn nguyên tên người đứng sau. Cột `deleted_at` giữ lại cho tình huống thật sự phải xoá dữ liệu cá nhân theo Nghị định 13 (mục 1.10).

#### PUT chứ không PATCH

Với PATCH, một trường mang giá trị `null` có **hai nghĩa không phân biệt được**: "xoá quản lý trực tiếp của người này" và "tôi không đụng tới trường quản lý". Tách hai nghĩa đó phải dựng thêm khái niệm "có gửi hay không" cho từng trường — chi phí chỉ đáng bỏ ra khi có nhiều chỗ gọi khác nhau, mà ở đây chỉ có đúng một form và nó gửi đủ mọi trường mỗi lần lưu. Với PUT thì `null` luôn có đúng một nghĩa: bỏ trống.

#### Ba thứ đi kèm, thiếu một là tính năng thành nguồn sinh lỗi

**1. Chặn tự đổi vai trò của chính mình.** Quản trị viên cuối cùng hạ vai trò mình xuống Nhân viên là mất `user.manage`, và không còn ai trong hệ thống nâng lại được. Cùng loại bẫy với `CannotDisableSelfException` đã có sẵn, chỉ khác cửa. Chặn ở chính mình là **đủ** — đổi vai trò người khác không tạo ra tình huống đó, vì người thao tác vẫn giữ nguyên quyền sau khi đổi. Giao diện khoá luôn ô chọn kèm câu giải thích, thay vì để bấm Lưu rồi mới báo lỗi.

**2. Chặn vòng lặp quản lý.** A quản lý B, B quản lý C, rồi đặt quản lý của A là C — sơ đồ tổ chức thành vòng tròn không có người đứng đầu. Đợt 1 chưa có chỗ nào duyệt ngược lên theo `manager_id` nên vòng lặp chưa gây treo, nhưng đợt 2 (duyệt đơn nghỉ phép theo cấp trên) thì có, và lúc đó dữ liệu hỏng đã nằm sẵn trong database từ lâu. Hàm kiểm tra đi ngược cả chuỗi và mang theo tập `$daQua` — thiếu nó thì gặp dữ liệu đã sẵn một vòng là treo cả request.

**3. Đổi phòng ban KHÔNG cần sửa dữ liệu nào khác** — và điều đó được khoá bằng test. Phạm vi task nhìn thấy tính ngay lúc truy vấn từ `users.department_id` (`Task::scopeVisibleTo`), nên đổi cột là đổi luôn phạm vi. Test `đổi phòng ban làm đổi luôn phạm vi task nhìn thấy` dựng hai phòng, kiểm trưởng phòng thấy gì trước và sau khi chuyển. Nếu sau này ai đó thêm một bảng đệm phạm vi, test đó sẽ đỏ và bắt phải xử lý việc cập nhật bảng đệm khi đổi phòng ban.

#### Cảnh báo, không phải lỗi

Chuyển một trưởng phòng sang phòng khác là thao tác **đúng**, chặn lại thì sai. Nhưng im lặng cũng sai: từ giây đó họ không còn nhìn thấy việc của đội cũ, và cấp dưới vẫn khai họ là quản lý trực tiếp. Phản hồi trả `meta.warnings`, giao diện hiện sau khi lưu thành công:

> Đổi phòng ban làm đổi luôn phạm vi công việc người này nhìn thấy: từ giờ họ xem được việc của phòng mới và không còn xem được việc của phòng cũ.
>
> Vẫn còn 3 nhân viên khai người này là quản lý trực tiếp. Nếu họ ở lại phòng cũ, cần đổi quản lý cho từng người.

Không đổi phòng ban thì `warnings` rỗng và hộp thoại đóng luôn — bắt bấm thêm một nút "Xong" chỉ để xác nhận điều đã thấy là phiền.

#### Nhật ký nhân sự

Bảng `user_activities`, cùng hình dạng với `task_activities`: chỉ ghi thêm, không sửa, không xoá, không soft delete. Ghi cả bảy loại biến cố — tạo, sửa hồ sơ, đổi vai trò, vô hiệu hoá, kích hoạt lại, đặt lại mật khẩu, gỡ 2FA.

**Là Action chứ không phải Observer**, khác hẳn `TaskActivityObserver`. Lý do: thay đổi nhân sự cần biết **ai** gây ra, mà Observer không có đường lấy người thao tác nếu không đi vòng qua `Auth` — và tầng Domain không được biết tới phiên đăng nhập. Hệ quả có chủ ý: sửa `users` bằng tay ở tinker hay seeder sẽ **không** sinh nhật ký. Bảng này ghi hành động của con người qua giao diện, không phải mọi lần cột bị ghi.

**Lưu TÊN phòng ban và chức vụ, không lưu khoá ngoại.** Nhật ký phải đọc được một mình nó. Lưu id thì một năm sau, khi phòng ban đã đổi tên hoặc bị gộp, dòng `department_id: 3 → 7` không còn nói lên điều gì.

**Chỉ ghi những trường thật sự đổi.** Ghi cả hồ sơ mỗi lần bấm Lưu thì nhật ký đầy dòng vô nghĩa, và tới lúc cần tra thì không ai đọc nữa. Bấm Lưu mà không đổi gì thì không sinh dòng nào.

**Ghi việc đã xảy ra, không ghi mật khẩu.** Nhật ký kiểm toán mà chứa thông tin xác thực thì bản thân nó thành chỗ rò rỉ — ai đọc được nhật ký sẽ đăng nhập được thay người khác. Có test khẳng định mật khẩu tạm vừa sinh không xuất hiện ở bất kỳ đâu trong bản ghi.

**Đọc nhật ký cần quyền `user.manage`, không phải quyền xem hồ sơ.** `UserPolicy::view` cho phép ai cũng xem hồ sơ của chính mình, nhưng nhật ký chứa cả những lần bị đổi vai trò hay bị vô hiệu hoá kèm tên người ra quyết định. Đó là thông tin quản trị, không phải hồ sơ cá nhân.

#### Kết quả kiểm thử

| Cổng | Kết quả |
|---|---|
| Pest | ✅ **362 passed** (3614 assertions) |
| Pint · Larastan mức 8 · Deptrac | ✅ Sạch |
| ESLint / Prettier / `tsc` / `next build` | ✅ PASS |

Thêm **28 test** trong `tests/Feature/Http/Auth/EmployeeEditTest.php`.

Đã **chạy thật qua HTTP** bằng token Sanctum, không chỉ qua test: đổi phòng ban và vai trò của một tài khoản demo → nhận đúng cảnh báo → đọc lại `GET /users/{id}/activities` thấy hai dòng nhật ký với tên phòng ban dạng chữ. Dữ liệu demo và token kiểm thử đã dọn sau khi xong.

#### Quyết định phát sinh khi làm phần này

**`quanTri()` từng nằm sai chỗ.** Hàm dựng tài khoản quản trị khai trong `EmployeeDirectoryTest.php`; file test mới dùng nhờ nó sẽ đỏ với "undefined function" khi chạy riêng, trong khi chạy cả bộ vẫn xanh. Đã chuyển sang `tests/Pest.php` — đúng cái bẫy mà chính file đó đã ghi chú từ mục 1.4, và vẫn mắc lại lần nữa.

**Ghi nhật ký nằm chung giao dịch với việc sửa.** Ghi hồ sơ xong mà nhật ký hỏng thì còn tệ hơn không ghi — lúc đó nhật ký nói dối chứ không chỉ là thiếu.

**Form thêm và form sửa là một component.** Các trường giống hệt nhau; tách ra thì mỗi lần thêm một trường vào hồ sơ lại phải nhớ sửa hai chỗ. Kèm một ràng buộc bắt buộc ghi rõ trong docblock: **chỗ gọi phải truyền `key` khác nhau cho từng nhân viên**, vì giá trị ban đầu của các ô nhập chỉ lấy đúng một lần lúc component gắn vào cây — thiếu `key` thì mở sửa người thứ hai vẫn hiện dữ liệu người thứ nhất rồi lưu đè.

#### Quyết định phát sinh

**API tạo nhân viên trước đây sinh ra tài khoản không ai vào được.** `CreateUserAction` đặt mật khẩu ngẫu nhiên rồi vứt đi — database chỉ lưu bản băm, nên không ai, kể cả quản trị viên, biết mật khẩu để đưa cho nhân viên mới. Quy trình thật sẽ là: tạo tài khoản, rồi bấm thêm "đặt lại mật khẩu" mới dùng được — tức là tạo ra một tài khoản chết rồi hồi sinh nó. Đã cho Action trả về `CreateUserResult` mang theo mật khẩu tạm, và API trả nó trong `meta.temporary_password` đúng một lần.

**Mật khẩu tạm phải chép tay được, không phải càng rối càng tốt.** `Str::password()` của Laravel sinh ra những chuỗi như `1DIqbw\a]ML3En.i` — có dấu chéo ngược, ngoặc vuông, và cặp `1`/`I` nhìn giống hệt nhau. Nhân sự đọc chuỗi đó qua điện thoại cho nhân viên mới là cầm chắc phải đọc lại ba lần, hoặc tệ hơn: người kia gõ sai năm lần rồi **bị khoá tài khoản năm phút vì chính cơ chế chống dò mật khẩu của mình**. Đã thay bằng `App\Support\TemporaryPassword`: bỏ hết ký hiệu và mọi ký tự dễ nhầm (`0 O o`, `1 l I`, `5 S`, `2 Z`, `8 B`), còn 49 ký tự, 16 ký tự cho ra ~90 bit ngẫu nhiên — thừa sức cho một chuỗi sống vài phút. Dùng chung cho cả đặt lại mật khẩu.

**Một quyền không được mở mọi cửa.** Hàm lọc mục điều hướng đang có luật "ai có `task.view.all` thì thấy mọi mục" — viết cho mục *Việc của đội*. Ngay khi thêm mục *Nhân sự*, giám đốc (có `task.view.all`, không có `user.manage`) sẽ nhìn thấy mục đó, bấm vào, và ăn 403. Đã đổi thành mỗi mục tự khai danh sách quyền của mình, có một trong số đó là thấy.

**Danh sách phòng ban và chức vụ không đặt Policy riêng.** Cơ cấu tổ chức là thông tin cả công ty vốn đã biết — nó nằm trên bảng tin, trong chữ ký email, trong mọi cuộc họp. Giấu nó chỉ làm form không dùng được mà không thêm an toàn nào.

**Mặc định danh sách chỉ hiện người đang làm việc.** Người đã nghỉ vẫn còn trong hệ thống để giữ lịch sử công việc, nhưng trộn lẫn vào danh sách nhân sự thì con số "công ty có bao nhiêu người" luôn sai.

**Lọc theo vai trò dùng scope `role()` của spatie.** Bản đầu tôi viết `whereHas('roles', fn ($r) => $r->where('name', ...))` — Larastan bắt ngay: bên trong closure của `whereHas`, tham số chỉ còn là Builder chung nên không kiểm được tên cột. Đây là lần thứ ba cùng một khuôn mắc lỗi này trong dự án.

### 1.10 Vận hành & đưa vào sử dụng

**Chuyển từ các mục trước sang đây** — đều cần môi trường staging hoặc quyết định của công ty, không làm được ở máy dev:

- [x] **Presigned URL có hạn** — đã làm, xem [Đường dẫn tệp đính kèm và Cloudflare R2](#đường-dẫn-tệp-đính-kèm-và-cloudflare-r2)
- [x] **Bật `MEDIA_DISK=r2`** — token đã cấp lại với quyền **Object Read & Write**, bật ngày 24/08/2026
- [ ] **Test trình duyệt thật** (Playwright) — test E2E hiện có đi qua HTTP đầy đủ nhưng không chạy JavaScript, nên không bắt được lỗi thuần frontend
- [ ] **Service worker cho PWA** — manifest đã có từ [mục 1.7](#17-giao-diện--đã-xong)
- [ ] **Rà soát Nghị định 13/2023/NĐ-CP** — phần kỹ thuật đã xong (xem bên dưới); còn lại là công ty xác định mục đích xử lý dữ liệu, chốt thời hạn lưu trữ và cử người chịu trách nhiệm — việc phải có chữ ký, không phải việc của mã nguồn

**Việc vận hành:**

- [x] **Sao lưu hằng ngày, mã hoá, và diễn tập phục hồi** — xem [Sao lưu và diễn tập phục hồi](#sao-lưu-và-diễn-tập-phục-hồi). **Đã chạy thật một vòng đầy đủ**, không phải chỉ viết ra
- [x] **Hạ tầng production** — `docker-compose.prod.yml`, `docker/php/Dockerfile.prod`, Supervisor giữ worker sống
- [x] **Deploy không gián đoạn + kịch bản quay lui** — `scripts/deploy.sh`, `scripts/rollback.sh`
- [x] **Xoay vòng log** — Docker json-file 3×20 MB mỗi container; MySQL bật slow query log
- [x] **Endpoint health check** cho database, Redis, R2 — `GET /api/v1/health`, xem [Kiểm tra tình trạng hạ tầng](#kiểm-tra-tình-trạng-hạ-tầng)
- [x] **Quy trình xoá dữ liệu người đã nghỉ việc** — `php artisan users:anonymise`, xem [Xoá dữ liệu cá nhân](#xoá-dữ-liệu-cá-nhân-người-đã-nghỉ-việc)
- [x] **Ngày nghỉ lễ** — ``HolidaySeeder`` 11 ngày/năm theo Điều 112 kèm nghỉ bù. Bảng này **từng trống rỗng**, nghĩa là hệ thống coi mùng 1 Tết là ngày làm việc
- [ ] Dựng môi trường staging giống production — **cần máy chủ**
- [ ] Cảnh báo lỗi ra ngoài (Sentry hoặc Slack webhook) — cần tài khoản dịch vụ
- [ ] Laravel Horizon cảnh báo khi job dồn — cần kênh nhận cảnh báo ở trên trước
- [ ] **Service worker cho PWA** — phần còn thiếu là chiến lược cache và cơ chế cập nhật. Cache sai trên ứng dụng dữ liệu sống thì nhân viên thấy danh sách việc của hôm kia mà không có cách nào tự sửa
- [ ] **Mã hoá ở tầng lưu trữ** (mã hoá đĩa hoặc TDE của MySQL) — cách bảo vệ cột lương đã chốt, xem [Vì sao cột lương không mã hoá ở tầng ứng dụng](#vì-sao-cột-lương-không-mã-hoá-ở-tầng-ứng-dụng). **Cần máy chủ**
- [ ] **Thu hẹp danh sách người có quyền vào database production** — cần máy chủ

**Kế hoạch go-live**

- [ ] Nhập dữ liệu ban đầu: danh sách nhân viên, phòng ban, dự án đang chạy
- [ ] Chốt cách nhập task đang dở dang từ cách làm cũ (Excel/Zalo) — nhập hay bỏ qua
- [ ] Chạy thử với **một phòng ban** trước khi mở toàn công ty
- [ ] Buổi hướng dẫn ngắn cho nhân viên và cho quản lý (hai nội dung khác nhau)
- [ ] Kênh tiếp nhận phản hồi và sửa lỗi trong 2 tuần đầu

---

## Các đợt tiếp theo

### Đợt 2 — Báo cáo tiến độ hằng ngày ✅ Phần chữ đã xong

Đây là **mảnh còn thiếu của ba tính năng đã làm**: chấm công cần nó để đối chiếu "có giờ nhưng không có việc gì", thưởng cần nó để chấm điểm đóng góp, tổng quan cần nó để nói về chất lượng chứ không chỉ số lượng.

- [x] Bảng `daily_reports` + `daily_report_tasks` — một báo cáo mỗi người mỗi ngày
- [x] Nhân viên viết, lưu nháp, nộp; tích các task đã đụng tới trong ngày
- [x] Màn của quản lý theo ngày — **kể cả người chưa nộp**
- [x] Đọc và nhận xét, có thông báo khi nhận xét thật
- [x] Nối vào chấm công: cờ `has_report` trên bảng công tháng + trạng thái báo cáo trong chi tiết ngày
- [ ] **Ảnh minh chứng** — công ty chọn chờ Cloudflare R2 (mục 1.10) rồi mới bật
- [x] **Nhắc lúc 17h30 nếu chưa nộp** — `php artisan reports:remind`, chạy tự động ngày làm việc. Xem [Nhắc nộp báo cáo](#nhắc-nộp-báo-cáo-cuối-ngày)
- [ ] **Tích hợp Zalo OA hoặc Telegram** cho thông báo — chờ biết công ty có Zalo OA không (câu hỏi mở số 2)

#### Nộp bù được bao nhiêu ngày

Hôm nay **và hai ngày liền trước** (`REPORT_BACKFILL_DAYS`). Ngày tương lai chặn hoàn toàn.

Trước khi có luật này, `date_format:Y-m-d` là **toàn bộ** ràng buộc. Gọi thẳng API thì nộp được báo cáo cho năm 2027, và nộp bù cả tháng trước bằng một vòng lặp curl — đã dựng lại đúng request đó để xác nhận trước khi sửa. Giao diện vốn đã có `max={hôm nay}` từ đầu, tức là **ý định có sẵn nhưng chưa bao giờ có hiệu lực**. Một luật chỉ nằm ở trình duyệt thì không phải là luật.

Hệ quả cụ thể nếu để nguyên: con số "số ngày thiếu báo cáo" ở trang Chấm công gian lận được sạch sẽ ngay trước kỳ đánh giá.

Vì sao là 2 chứ không phải 0 hay 30:

- **0** thì ốm một hôm là mất luôn, và người ta sẽ nhắn admin xin ngoại lệ — tức là chuyển việc từ hệ thống sang hộp thư của admin.
- **Cả tháng** thì nộp bù hàng loạt sát kỳ đánh giá, và cột đối chiếu mất hết ý nghĩa.

Luật có hiệu lực ở **`SaveDailyReportAction`**, không chỉ ở `FormRequest`: đây là chính sách nghiệp vụ, không phải luật định dạng dữ liệu. Chặn ở tầng nhận request thì bất kỳ đường nào khác gọi tới Action sau này — lệnh nhập liệu, job đồng bộ — đều đi vòng qua được. Cả hai đọc chung `App\Domain\Report\Data\ReportWindow` nên không có đường nào lệch.

Ranh giới ngày lấy theo **giờ Việt Nam**, không dùng `today` của Laravel: ứng dụng chạy UTC, nên từ 00:00 tới 07:00 giờ Việt Nam mỗi ngày `today` vẫn còn là hôm qua — người làm ca sáng sớm sẽ không nộp được báo cáo của chính ngày họ đang làm. Có test khoá lại mốc đó.

API trả thêm `window.earliest` / `window.latest` để giao diện **đóng ô soạn** thay vì để người ta gõ xong vài trăm chữ rồi mới ăn 422. Frontend không tự tính từ `new Date()`: đồng hồ máy người dùng có thể lệch, và nhân viên đi công tác có thể đang ở múi giờ khác.

**Duyệt ngày công** chặn cùng khuôn nhưng **chỉ phía tương lai** — quản lý vẫn phải xử lý được bảng công tháng trước. Khoá kỳ công là việc của đợt 4.

#### Nhắc nộp báo cáo cuối ngày

```bash
php artisan reports:remind                    # hôm nay
php artisan reports:remind --date=2026-08-10  # một ngày đã qua
php artisan reports:remind --dry-run          # chỉ liệt kê, không gửi
```

Chạy tự động **17h30 các ngày làm việc** (`REPORT_REMINDER_AT`, giờ Việt Nam). Tắt bằng `REPORT_REMINDER_ENABLED=false`.

##### Chỉ nhắc người thật sự có giờ làm hôm nay

Đây là quyết định quan trọng nhất của phần này. Nhắc toàn bộ nhân sự thì người nghỉ phép, nghỉ ốm, đi công tác đều nhận — và **chỉ cần vài lần như thế là cả công ty coi thông báo của hệ thống là tiếng ồn**, kể cả loại quan trọng.

Đánh đổi đã biết và chấp nhận có chủ ý: người làm việc **ngoài hệ thống** cả ngày sẽ không có giờ nên **không được nhắc**, dù họ vẫn cần báo cáo. Nhắc nhầm gây hại nhiều hơn nhắc sót, và bảng đối chiếu ở trang Chấm công vẫn hiện ngày trống cho quản lý thấy. Có quỹ phép ở đợt 4 rồi thì mở rộng được — lúc đó mới phân biệt được "không làm" với "nghỉ có phép".

##### Là lệnh Artisan chứ không phải Job, vì lý do kiến trúc

Việc này đọc cả miền Attendance (giờ làm) lẫn miền Report (báo cáo), mà hai miền nghiệp vụ **không được gọi nhau** — deptrac chặn. Tầng `Console` là một trong hai chỗ được phép biết nhiều miền cùng lúc (chỗ kia là `Http`). Đặt vào `Domain/Report/Jobs` là vi phạm ngay từ dòng `use` đầu tiên.

Lệnh không gửi email đồng bộ: `PreferenceAwareNotification` đã là `ShouldQueue` nên mỗi thông báo chỉ được đẩy vào hàng đợi.

##### Chạy lại không gửi trùng

Người vận hành chạy lại lệnh để kiểm tra là chuyện bình thường; không có lớp chặn thì lần thứ hai gửi lại cho đúng những người vừa nhận, và lời nhắc mất hết uy tín ngay ngày đầu.

Lọc theo `data->report_date` trong bảng `notifications` chứ **không theo ngày tạo**: thông báo đi qua hàng đợi nên `created_at` là lúc worker xử lý, có thể rơi sang hôm sau nếu hàng đợi dồn.

##### Loại thông báo này bật email mặc định

Ngược với `report.reviewed`. Lý do: nó bắn lúc 17h30, đúng lúc nhiều người đã đóng trình duyệt — thông báo trong ứng dụng lúc đó không tới được ai, và tính năng thành vô dụng với chính nhóm nó nhắm tới. Bù lại nó gửi tối đa **một lần mỗi người mỗi ngày**, và chỉ khi thật sự có việc phải làm.

#### Một báo cáo mỗi ngày, không phải mỗi task

Quyết định gốc của cả đợt, và nó quyết định mọi thứ còn lại.

Gắn báo cáo vào từng task thì câu **"hôm nay ai chưa báo cáo"** không có đáp án — người họp cả ngày hoặc hỗ trợ đồng nghiệp không có task nào để gắn vào, nên họ biến mất khỏi danh sách. Mà đó chính là câu chấm công cần, và là câu quản lý mở màn hình lên để hỏi.

Nên màn của quản lý liệt kê **mọi nhân sự trong phạm vi**, người chưa nộp thì `report` là `null`. Danh sách task trong báo cáo là **tuỳ chọn**: bắt buộc phải có task là ràng buộc khiến người ta bịa ra một việc để nộp cho xong.

#### Đọc, không phải duyệt

Không có "duyệt / từ chối". Báo cáo ngày là thứ nhân viên kể lại việc mình đã làm — không có gì để phê duyệt. Cái quản lý làm là **đọc**, và **hỏi lại** khi cần. Dựng thành luồng duyệt sẽ biến nó thành thủ tục xin phép, và người ta sẽ viết cho qua chứ không viết thật.

Nhận xét **không bắt buộc**: bắt ghi nhận xét mỗi ngày cho mỗi người là cách nhanh nhất khiến quản lý bỏ luôn việc đọc. Chỉ khi có nhận xét thật mới gửi thông báo — đánh dấu đã đọc mà cũng báo thì mỗi sáng nhân viên nhận một thông báo không cần làm gì.

#### Ba luật nhỏ, mỗi cái chặn một cách hỏng

**Nội dung tối thiểu 10 ký tự.** Không có mức sàn thì trường này đầy những dòng "ok", "làm việc", "như hôm qua" — và lúc đó báo cáo ngày trở thành nghi thức bấm nút chứ không còn là thứ quản lý đọc được.

**Nộp rồi vẫn sửa được, quản lý đọc rồi thì thôi.** Khoá ngay sau khi nộp chỉ khiến người ta ngại nộp sớm rồi dồn hết vào cuối tuần. Nhưng khi đã có người đọc thì sửa là đổi thứ người khác đã đọc và đã dựa vào để nhận xét.

**Mốc nộp giữ lần đầu.** Sửa lại sau khi nộp không làm `submitted_at` lùi tới — câu "nộp muộn không" phải trả lời bằng lần nộp đầu.

**Bản nháp là của riêng người viết.** Quản lý thấy "còn là bản nháp" nhưng không đọc được nội dung, và không nhận xét được. Cho phép nhận xét lên bản nháp nghĩa là đọc câu chữ mà nhân viên chưa muốn cho ai xem.

#### Kiểm quyền xem task, không tin danh sách client gửi

Endpoint nộp báo cáo nhận danh sách uuid task. Không kiểm thì **bất kỳ ai cũng dò được tiêu đề task của phòng khác** bằng cách nhét uuid vào báo cáo rồi đọc lại phản hồi — đường này ai đăng nhập cũng gọi được nên nó là bề mặt tấn công thật. Mỗi uuid đi qua `Task::visibleTo($actor)` trong một truy vấn duy nhất; thiếu cái nào thì 422 mà không tiết lộ gì. Có test khẳng định tiêu đề task của phòng khác không xuất hiện trong phản hồi lỗi.

#### Ranh giới miền

`Report → Identity, Support` — không được gọi sang Task. Nên bảng `daily_report_tasks` giữ khoá ngoại `task_id` thật ở database nhưng model `DailyReport` **không** khai quan hệ tới `Task`; tầng Http ghép tên task vào. Cùng khuôn đã dùng ba lần trước đó: bảng lương ghép với `User`, quỹ thưởng ghép với dự án, và giờ báo cáo ghép với task.

Cờ `has_report` trên bảng công cũng ghép ở tầng Http vì `SummariseAttendanceAction` thuộc miền Attendance.

#### Kết quả kiểm thử

| Cổng | Kết quả |
|---|---|
| Pest | ✅ **683 passed** (4680 assertions) |
| Pint · Larastan mức 8 · Deptrac | ✅ Sạch |
| ESLint / Prettier / `tsc` / `next build` | ✅ PASS |

Thêm **24 test** trong `tests/Feature/Http/Reports/DailyReportTest.php`.

Đã **chạy thật qua HTTP**: nhân viên nộp báo cáo → màn quản lý hiện `1/7 người đã nộp` kèm tên từng người chưa nộp; chi tiết ngày công hiện `phiên: 1 · lần đụng việc: 0 · báo cáo: Đã nộp`; bảng công tháng hiện `has_report` đúng cho từng người; nhân viên gọi `/reports/team` → `403`. Dữ liệu và token kiểm thử đã dọn.

#### Quyết định phát sinh

**Quyền báo cáo suýt rơi nhầm vai trò.** Chuỗi thay thế tôi dùng để thêm `ViewTeamReports` và `ReviewReports` khớp trúng khối của **Giám đốc** thay vì **Trưởng phòng**, nên bảy test đỏ với `403`. Không có test thì lỗi này lọt êm — trưởng phòng vẫn đăng nhập được, chỉ là không mở nổi màn báo cáo của phòng mình, và người ta sẽ báo là "hệ thống lỗi" chứ không ai nghĩ tới phân quyền.

**Chính test của tôi dính bẫy múi giờ.** Tôi đặt `travelTo('2026-08-12 17:00')` cho giống "cuối giờ chiều" — nhưng 17:00 UTC là **00:00 ngày 13** giờ Việt Nam, nên nhịp tim ghi vào ngày công 13 trong khi báo cáo ghi ngày 12, và cờ `has_report` không khớp. Đúng cái bẫy mà cột `work_date` sinh ra để chặn, lần này bẫy người viết test. Đã đổi sang 03:00 UTC kèm chú thích.

### Đợt 3 — Chấm công ✅ Phần đo đã xong

Không dùng GPS, không selfie, không theo dõi màn hình — công ty làm remote, những thứ đó vừa vô nghĩa vừa phá niềm tin.

**Làm sớm hơn lộ trình** vì công ty đang chấm công bằng cách cho nhân viên *treo một trang web* rồi đo thời gian tab mở. Cách đó sai theo cả hai chiều cùng lúc: mở tab rồi đi làm việc khác thì qua được trong 0 giây, còn người đang làm thật trên Word hay Zalo thì bị tính là không làm.

- [x] Bảng `work_sessions` — phiên làm việc suy ra từ **tương tác thật**, không phải tab mở
- [x] Bảng `work_days` — quyết định của người quản lý, bảng thưa, lý do bắt buộc
- [x] Bảng `holidays` — có `observed_date` cho nghỉ bù; **Tết âm lịch trôi theo năm nên không hardcode**
- [x] Nhịp tim theo tương tác, khoảng lặng quá 10 phút thì cắt phiên và **không tính**
- [x] Màn giờ làm của chính mình + bảng công tháng của cả phòng
- [x] Ghi nhận / bỏ qua / cần hỏi lại — kèm lý do, không tự trừ lương
- [x] Đối chiếu tự động với **báo cáo ngày** — xem [Đối chiếu giờ công với báo cáo](#đối-chiếu-giờ-công-với-báo-cáo-ngày)
- [ ] Xuất Excel cho kế toán — chờ biết kế toán dùng phần mềm gì
- [ ] `work_shifts`, `attendance_policies` — **hoãn có chủ ý**, xem bên dưới

#### Đối chiếu giờ công với báo cáo ngày

Mảnh cuối của đợt 3, làm được ngay sau khi đợt 2 xong. Cột `has_report` đã có từ trước, nhưng **một ô đúng/sai trên lưới ba mươi ngày × ba mươi người là chín trăm thứ để mắt tự lọc** — tức là không ai lọc.

`App\Support\Enums\ReportMatch` biến nó thành bốn tình huống có tên:

| Trạng thái | Nghĩa | Cần ai nhìn? |
|---|---|---|
| `ok` | Có giờ làm, có báo cáo | Không |
| `missing_report` | Có giờ làm ≥ 60 phút, **không có báo cáo** | **Có** |
| `report_only` | Có báo cáo, hệ thống ghi được rất ít giờ | Không |
| `idle` | Không giờ, không báo cáo | Không |
| `holiday` | Ngày lễ — không đối chiếu | Không |

**Chỉ một trong năm là thứ cần để mắt.** `report_only` cố ý không tính là bất thường: họp cả ngày, đi gặp khách, làm trên Word đều rơi vào đó. Đếm nó là bất thường thì người ta sẽ mở sẵn ứng dụng cho đủ giờ — đúng thói quen mà cả tính năng chấm công này sinh ra để bỏ.

Ngày quản lý đã xử lý (`work_days.decision`) thì **thôi đếm**. Cờ vẫn còn để tra lại, nhưng con số "cần xem" phải về được 0, nếu không người ta ngừng nhìn nó.

Trên giao diện: một **chấm nhỏ ở góc ô**, không phải tô đỏ cả ô. Quên nộp báo cáo không phải vi phạm, và tô đỏ ba mươi ô sẽ biến bảng công thành bảng buộc tội. Màn của chính nhân viên hiện trước màn của quản lý — tự thấy rồi tự bù thì không cần ai hỏi tới.

##### Một lỗ hổng tìm ra khi rà lại, không phải khi viết test

Ô ngày được dựng từ bảng `work_sessions`. Nghĩa là **người họp cả ngày rồi tối về vẫn ngồi viết báo cáo sẽ không có ô nào** — quản lý nhìn ô trắng và đọc ra "hôm đó không làm gì". Ngược hẳn sự thật, và chính người chịu khó báo cáo lại là người bị hiểu sai. Không có test nào đỏ, vì test chỉ kiểm những gì mình nghĩ ra.

Đã vá: ngày có báo cáo mà không có phiên nào vẫn sinh ra một ô 0 phút, đi qua đúng hàm dựng ô như mọi ngày khác. Hai test khoá lại.

##### Vì sao luật đối chiếu nằm ở `Support` chứ không ở `Domain`

Nó bắc qua hai miền: Attendance đo giờ, Report giữ báo cáo, mà **hai miền nghiệp vụ không được gọi nhau**. Enum chỉ nhận số nguyên và boolean nên không phụ thuộc miền nào — và nhờ vậy kiểm thử được thẳng, không cần dựng database. Mốc "có làm" **truyền vào chứ không tự đọc `config()`**: một enum tự móc vào container là một enum không unit test được.

Phần ghép dữ liệu nằm ở `App\Http\Concerns\ReconcilesWithDailyReports` — tầng Http là chỗ duy nhất biết cả hai miền, cùng khuôn với `PresentsDailyReports`.

#### Chính sách: nhìn cho biết, duyệt tuỳ hoàn cảnh

Chốt với công ty trước khi viết dòng nào: con số giờ **không tự trừ vào lương**. Hệ thống đo và gắn cờ, con người quyết định. Điều đó cắt bớt hẳn phạm vi so với kế hoạch ban đầu:

| Kế hoạch cũ | Thực tế đã làm | Vì sao |
|---|---|---|
| `attendances` bấm vào/ra + IP + thiết bị | `work_sessions` suy ra từ tương tác | Nút bấm tay thì người ta quên; IP/thiết bị không có mục đích dùng nên thu thập là vi phạm nguyên tắc tối thiểu hoá dữ liệu của Nghị định 13 |
| `work_shifts` — ca chuẩn theo phòng | Hoãn | Giờ giấc đang linh hoạt, "đi muộn" chưa có nghĩa |
| `attendance_policies` | Hoãn | Một chính sách thì chưa cần bảng chính sách. Cần khi có nhóm onsite |
| Khoá kỳ công (đợt 4) | Giữ ở đợt 4 | Không trừ lương tự động thì chưa cần khoá sổ |

**Thứ giữ lại từ đợt 4 và kéo lên ngay: lý do bắt buộc.** *"Duyệt không trừ tuỳ hoàn cảnh"* chính là loại quyết định sinh tranh cãi sáu tháng sau — *"sao tháng trước anh bỏ qua cho tôi mà tháng này lại tính?"* Không ghi ai quyết định và vì sao thì không ai trả lời được. Lý do tối thiểu 5 ký tự: không có mức sàn thì trường này đầy những dòng "ok" và "x", vẫn không ai hiểu gì mà lại tưởng đã ghi.

#### Bẫy múi giờ — cột `work_date` tồn tại chỉ để chặn nó

Đây là lỗi đắt nhất của mọi hệ thống chấm công, và nó **không tự lộ ra**: dữ liệu vẫn lưu được, vẫn đọc ra được, chỉ sai ngày.

```
Nhân viên làm tới 00:30 sáng giờ Việt Nam:

  started_at (UTC)      2026-08-11 17:30
  giờ Việt Nam          2026-08-12 00:30
  ngày công đúng        2026-08-12   ← work_date lưu cái này
  DATE(started_at)      2026-08-11   ← sai, và không ai nhận ra
```

`App\Support\Time\WorkDate` là nơi duy nhất định nghĩa ranh giới ngày công (00:00 giờ Việt Nam), tính một lần lúc ghi rồi lưu thành cột riêng. Bổ sung cho `IncomingDateTime` ở mục 1.6: lớp kia chuẩn hoá giờ nhận từ client về UTC, lớp này chuyển giờ UTC thành ngày công để gom nhóm. Cast của `work_date` cố ý là `string` chứ không phải `date` — cast thành Carbon sẽ gắn thêm 00:00 theo múi giờ ứng dụng và mở lại đúng cái bẫy mà cột này sinh ra để chặn.

Hai test khoá lại: một cho mốc 17:30 UTC, một cho phiên vắt qua nửa đêm bị cắt làm hai ngày dù chỉ cách nhau 4 phút.

#### Chi phí một nhịp tim: 3 truy vấn, khoá bằng test

Đây là đường được gọi nhiều nhất cả hệ thống — hai trăm nhân sự × tám tiếng ≈ **96.000 lượt mỗi ngày**. Mỗi truy vấn thừa ở đây nhân lên chín mươi sáu nghìn lần.

Đo thật thì nó tốn **sáu**, và **ba trong đó là nạp vai trò với quyền** — cho một endpoint không kiểm quyền nào cả, ai đăng nhập cũng gửi được nhịp của chính mình. Ba truy vấn cho một câu hỏi không ai đặt ra, 288.000 lượt mỗi ngày.

Nguồn gốc: middleware `active` nạp sẵn `roles.permissions` cho **mọi** request. Việc đó đúng và cần thiết ở mọi endpoint khác — không nạp trước thì `preventLazyLoading` ném lỗi, hoặc thành N+1 âm thầm trên production.

**Cách sửa:** route tự khai mình không cần quyền.

```php
Route::post('/attendance/heartbeat', HeartbeatController::class)
    ->defaults('preload_permissions', false);
```

Mặc định vẫn là **có nạp**. Quên khai thì chỉ tốn ba truy vấn; nếu mặc định là không nạp thì quên khai là ăn N+1 âm thầm. Sai theo hướng an toàn.

Và nó **tự bảo vệ**: ai thêm một phép kiểm quyền vào route đã khai `false` sẽ gặp lỗi `preventLazyLoading` ngay ở môi trường dev, thay vì để lọt.

Ba truy vấn còn lại là phần việc thật: tìm phiên gần nhất, ghi phiên, tổng hợp số phút hôm nay. Có test khoá con số — ai thêm truy vấn vào đường này sẽ thấy test đỏ và phải quyết định có đáng hay không.

#### Vì sao không lưu từng nhịp tim

Một nhịp mỗi phút × 8 tiếng × 200 nhân sự × 22 ngày công ≈ **2,1 triệu dòng mỗi tháng** cho một thông tin không ai đọc. Nhịp tim tới thì nối dài `ended_at` của phiên đang mở; cách phiên gần nhất quá 10 phút thì mở phiên mới. Còn lại **3–6 dòng mỗi người mỗi ngày**.

**Không có khái niệm "ra ca".** Sập nắp laptop, mất điện, mất mạng, đóng trình duyệt trên điện thoại — `beforeunload` không chạy. Thiết kế theo cặp *vào ca → ra ca* sẽ để lại những phiên không bao giờ đóng, và bảng công hiện ai đó làm 47 tiếng liên tục. Ở đây phiên **tự kết thúc** ở nhịp cuối cùng nhận được.

#### Bảng công đọc theo LỊCH, không theo dãy 31 ô

Bản đầu vẽ 31 ô bằng `flex-wrap`. Nó chạy được, nhìn cũng gọn — và **rất khó theo dõi**, vì một lý do không ai gọi tên ngay được: số ô mỗi hàng phụ thuộc chiều rộng cửa sổ. Thứ Hai không bao giờ nằm cùng một cột.

Hệ quả: mắt không có mốc nào. Không thấy cuối tuần, không thấy "tuần này nghỉ ba ngày liền", không thấy "thứ Sáu nào cũng trống". Cùng dữ liệu, cùng màu, nhưng người đọc phải tự dựng lại cấu trúc tuần trong đầu ở mỗi lần nhìn.

Giờ là **lưới bảy cột cố định theo thứ, hàng là tuần** — đúng cách người ta đọc lịch. Chuỗi ngày nghỉ hiện thành một mảng liền, ngày trống giữa tuần bật ra khỏi nền cuối tuần.

Bảng của quản lý giữ nguyên dạng bảng (31 cột là đúng cho việc so sánh giữa người với người), nhưng thêm **header hai tầng** — thứ ở trên, ngày ở dưới — và nền chìm cho cột cuối tuần. Trước đó header chỉ có `1 2 3 … 31`, nên cuộn ngang giữa một biển ô giống hệt nhau mà không có gì tách tuần.

##### Cái bẫy múi giờ khi dựng lịch, đã đo chứ không đoán

`new Date("2026-08-01").getDay()` là cách viết tự nhiên nhất, và nó **sai**. Chuỗi chỉ có ngày được hiểu là nửa đêm UTC, còn `getDay()` đọc theo giờ địa phương:

| Múi giờ | `getDay()` trả về |
|---|---|
| Hà Nội UTC+7 | T7 ✓ |
| UTC | T7 ✓ |
| New York UTC−4 | **T6** ✗ |
| Honolulu UTC−10 | **T6** ✗ |

Cả lịch lệch một cột. Lỗi này **không bao giờ lộ ra ở Việt Nam** — chỉ lộ khi có người mở từ châu Âu hay châu Mỹ, tức là đúng lúc không ai ngồi cạnh để phát hiện. Dùng `Date.UTC()` + `getUTCDay()` thì đúng ở mọi múi giờ.

##### Một ô hiển thị sai nghĩa

Ô trong bảng đội dùng `Math.round(phút / 60)`. Ngày làm **29 phút ra "0"** — nhìn gần như y hệt ô trống, và quản lý đọc thành "không làm gì" trong khi thực tế là "có mở máy nửa tiếng". Sai theo hướng nguy hiểm nhất: nó không trông như lỗi, nó trông như một sự thật. Giờ hiện `<1`.

##### Con số đứng trước, lưới đứng sau

Thêm một hàng bốn ô tóm tắt phía trên lịch: tổng giờ · ngày có làm · ngày nghỉ phép · ngày thiếu báo cáo. Người ta mở trang này để biết *tháng này thế nào*, không phải để đếm ô — lưới lịch là chỗ tra lại khi cần biết ngày cụ thể.

Và một **chú giải màu** dưới lịch. Màn này có sáu trạng thái màu cộng một chấm, mà trước đây không chỗ nào nói chúng nghĩa gì — người dùng phải rê chuột từng ô để đoán.

#### Bốn cái bẫy trình duyệt, xử lý ở `use-heartbeat.ts`

| Bẫy | Cách xử lý |
|---|---|
| Tab nền bị bóp xuống 1 nhịp/phút rồi đóng băng | Không đếm giờ bằng bộ đếm trong tab; backend tính từ mốc thời gian |
| Đóng máy đột ngột không bắn sự kiện | Phiên tự kết thúc ở nhịp cuối, không chờ ai báo |
| Nhiều tab cùng mở | Backend gộp theo người trên trục thời gian, mười tab vẫn ra một phiên |
| Máy ngủ rồi thức, `setInterval` bắn bù dồn dập | Cờ chặn chồng request; khoảng lặng vượt ngưỡng bị cắt khỏi tổng |

Nhịp chỉ gửi khi có `pointerdown`/`keydown`/`wheel`/`touchstart` trong phút vừa rồi **và** tab đang hiển thị. Cố ý không dùng `mousemove`: rê chuột qua màn hình lúc đi ngang bàn không phải là làm việc.

#### Ba điều nói thẳng với nhân viên

**Nhân viên nhìn thấy đúng con số mà quản lý nhìn thấy**, theo thời gian thực, ngay trên đầu trang. Đó là khác biệt giữa tự theo dõi và bị theo dõi lén — và là điều kiện để tính năng này không phá niềm tin.

**Không chống được gian lận, và không giả vờ chống được.** Ai muốn lắc chuột tự động thì lắc được. Điểm mạnh không nằm ở con số giờ mà ở chỗ nó **đứng cạnh** số phiên và số lần đụng vào công việc: sáu tiếng online mà không động vào việc nào thì nhìn phát ra ngay — thứ mà con số giờ đơn độc không bao giờ nói được.

**Ô ngày công cố ý không có màu đỏ.** Giờ thấp không phải lỗi: có thể là nghỉ phép, họp ngoài, hoặc làm việc trên công cụ khác. Đỏ ở đây sẽ biến bảng công thành bảng buộc tội.

#### Kết quả kiểm thử

| Cổng | Kết quả |
|---|---|
| Pest | ✅ **405 passed** (3749 assertions) |
| Pint · Larastan mức 8 · Deptrac | ✅ Sạch |
| ESLint / Prettier / `tsc` / `next build` | ✅ PASS |

Thêm **27 test**: nhịp tim và ranh giới ngày công (11), bảng công đội, phạm vi quyền và duyệt (16). Có test khoá số truy vấn không tăng theo số nhân sự.

#### Quyết định phát sinh

**Seeder không đưa được quyền mới tới vai trò đã tồn tại.** `RolePermissionSeeder` thoát ngay khi thấy vai trò đã có, để không ghi đè tuỳ chỉnh của công ty. Nhưng vậy thì ba quyền chấm công thêm ở đợt này **sẽ không tới được vai trò nào trên hệ thống đang chạy**, và không có gì báo — tính năng chỉ đơn giản là không ai vào được. Đã sửa: seeder ghi nhận quyền nào **vừa mới ra đời trong lượt chạy này** và chỉ cấp thêm những quyền đó theo bộ mặc định của vai trò; tuỳ chỉnh cũ không bị đụng tới. Kiểm chứng bằng cách xoá ba quyền rồi chạy lại seeder.

**`cells` trả về `[]` thay vì `{}` khi rỗng.** Mảng PHP rỗng được `json_encode` thành mảng, nên người chưa có ngày công nào trả về một **mảng** trong khi mọi người khác trả về object. Client tra `cells["2026-08-01"]` trên mảng thì im lặng ra `undefined` — không lỗi, chỉ sai. Test không bắt được vì `assertJsonPath` không phân biệt hai kiểu; chỉ lộ ra khi gọi thật bằng `curl`. Đã ép `(object)` và thêm test đọc JSON thô để so kiểu.

**Test đầu tiên tôi viết sai dữ liệu, không phải mã sai.** Tôi đặt hai nhịp cách nhau 30 phút rồi mong chúng nối thành một phiên 30 phút — trong khi ngưỡng nối là 10 phút, nên đó là hai phiên rỗng. Đã thêm hàm `lamViec()` mô phỏng nhịp mỗi 5 phút, kèm chú thích để người sau không mắc lại.

### Nghỉ phép ✅ Đơn + duyệt + miễn chấm công đã xong

Làm sớm hơn lộ trình, cắt ra khỏi đợt 4. Lý do: **càng làm chấm công chặt mà chưa có nghỉ phép thì càng sinh việc tay**. Trước phần này, mỗi ngày nghỉ của mỗi người để lại một ô trống không rõ nguyên nhân trên bảng công, và cách duy nhất để dọn là trưởng phòng bấm "Bỏ qua" kèm lý do tối thiểu 5 ký tự. Vài chục lần mỗi tháng, để ghi lại một thông tin mà chính nhân viên đã biết từ đầu.

| Trước | Sau |
|---|---|
| Nhân viên nhắn Zalo, trưởng phòng gật đầu, không ai ghi lại | `/leave` — nộp đơn, duyệt, có vết |
| Ngày nghỉ = ô trống, quản lý bấm tay từng ngày | Ô hiện "Nghỉ", tự miễn chấm công |
| Lệnh nhắc 17h30 vẫn bắn cho người đang nghỉ | Bỏ qua người có đơn đã duyệt |

- [x] `leave_requests` — đơn nghỉ theo khoảng ngày, lý do bắt buộc
- [x] Duyệt / từ chối, kèm thông báo cho cả hai phía
- [x] **Ngày đã duyệt được miễn chấm công** — bảng công hiện "Nghỉ phép đã duyệt"
- [x] Người nộp tự rút được đơn khi còn đang chờ
- [ ] Nghỉ nửa ngày · duyệt nhiều cấp — cần công ty chốt chính sách trước

#### Phạm vi cố ý hẹp

**Không có quỹ phép, không có số ngày còn lại, không nghỉ nửa ngày.** Ba thứ đó đều cần công ty chốt chính sách trước: một năm bao nhiêu ngày phép, phép tồn có chuyển sang năm sau không, nghỉ nửa ngày tính công thế nào.

Nhưng **không cần biết quỹ phép cũng gỡ được việc bấm tay hằng ngày** — và đó là toàn bộ mục tiêu của phần này. Loại nghỉ vẫn tách sẵn thành enum (`LeaveType`) chứ không gộp thành ô ghi chú tự do, để khi có quỹ phép thì đó là chỗ gắn "loại nào trừ quỹ nào".

#### Lưu khoảng ngày, không lưu từng ngày

Một đơn nghỉ ba ngày là **một** quyết định của quản lý, không phải ba. Tách thành ba dòng thì duyệt được hai ngày và bỏ sót ngày thứ ba, và không còn chỗ nào giữ lý do chung.

Đổi lại, mọi câu hỏi "ngày X có nghỉ không" thành so sánh khoảng. Hai khoảng giao nhau khi và chỉ khi `start <= den` VÀ `end >= tu` — viết đúng một lần ở scope `approvedBetween` thay vì để mỗi chỗ dùng tự nghĩ lại, vì đảo nhầm một dấu là lọc ra tập rỗng và **không có gì báo**.

#### Điều dễ hỏng âm thầm nhất

Người nghỉ phép **không có phiên làm việc nào** — họ không đụng vào hệ thống. Mà ô trên bảng công lại được dựng từ bảng `work_sessions`.

Quên xử lý thì ngày nghỉ đã duyệt đơn giản là **không xuất hiện**: không lỗi, không cảnh báo, ô vẫn trống y hệt ngày vắng mặt không lý do — và cả tính năng vô dụng trong khi mọi test khác vẫn xanh. Đó là lý do có `leaveOnlyDays()` sinh ô 0 phút, cùng khuôn với `reportOnlyDays()` làm trước đó, và có test khoá riêng cho đúng tình huống này.

#### Ba ràng buộc và lý do của chúng

**Chặn đơn chồng lấn.** Không chặn thì một ngày thuộc hai đơn, và câu "ngày này nghỉ theo đơn nào, ai duyệt, lý do gì" hết đáp án duy nhất. Tệ hơn: duyệt đơn này rồi từ chối đơn kia thì cùng một ngày vừa được miễn chấm công vừa không. Dùng `lockForUpdate` vì bấm đúp nút Nộp là hai request gần như cùng lúc, cả hai đều thấy "chưa có đơn nào trùng".

Đơn **bị từ chối thì không chặn** đơn nộp lại — bị từ chối rồi nộp lại với lý do rõ hơn là chuyện bình thường.

**Đơn đã duyệt không quay lại được.** Ngày đã duyệt là căn cứ miễn chấm công; rút ngược lại nghĩa là bảng công của một ngày trong quá khứ đổi nghĩa mà không ai biết. Cùng nguyên tắc với quỹ thưởng đã chốt.

**Không tự duyệt đơn của chính mình**, kể cả khi có quyền. Ràng buộc này **không suy ra được từ phạm vi phòng ban** — trưởng phòng luôn nằm trong phòng của chính mình, nên nếu chỉ kiểm phạm vi thì họ tự duyệt được.

#### Nộp bù cho quá khứ mở khá rộng, có chủ ý

`LEAVE_BACKDATE_DAYS` mặc định 90 ngày. Nghỉ ốm đột xuất thường được khai **sau khi đã nghỉ**, và đó chính là trường hợp cần miễn chấm công nhất. Chặn quá chặt thì người ta quay lại nhắn Zalo — tức là đẩy việc ra khỏi hệ thống, đúng thứ tính năng này sinh ra để gom vào.

Vẫn có mốc: không có thì nộp được đơn nghỉ cho năm 2020, và bảng công của một kỳ đã chốt đổi nghĩa. Cộng thêm trần 60 ngày một đơn để chặn lỗi gõ nhầm năm — "từ 12/08/2026 đến 12/08/2027" duyệt nhầm là cả năm miễn chấm công.

---

### Ca làm chuẩn & xin đi làm muộn ✅ Đã xong

**8h15–12h, 13h30–17h30** — 465 phút một ngày. Công ty chốt tháng 8/2026.

- [x] `attendance.shift` — giờ ca trong config, kèm `grace_minutes` (mặc định 0)
- [x] `WorkShift` — quy UTC sang giờ Việt Nam rồi mới so, không so chuỗi thẳng
- [x] Bảng công có `late_minutes` và `late_excused`, hiện bằng chấm ở góc trái ô
- [x] `late_arrival_requests` — đơn xin đi muộn theo GIỜ: nộp / duyệt / từ chối / rút
- [x] **Đơn đã duyệt chỉ bao tới đúng giờ đã xin**
- [x] Lý do đơn bị xoá khi ẩn danh nhân sự (Nghị định 13)

#### Đây là một thay đổi về chính sách, không chỉ là thêm tính năng

Trước đó chấm công **cố ý** không có khái niệm giờ vào làm. Chú thích trong `config/attendance.php` từng nói thẳng: *"Công ty làm remote với giờ giấc linh hoạt"*. Hệ thống chỉ đo **tổng số phút trong ngày** — người làm 10h–19h và người làm 8h–17h là như nhau.

Công ty đã chốt giờ cố định nên mốc đó xuất hiện. Nhưng cái cũ **không mất đi**: tổng số phút vẫn là thứ chính để đối chiếu với báo cáo ngày. Giờ vào làm là một cột thông tin **thêm bên cạnh**, không phải thứ thay thế.

#### Ranh giới quan trọng nhất: đi muộn KHÔNG cắt giờ công

Đến muộn 15 phút rồi làm 300 phút vẫn được ghi nhận đủ **300 phút**.

Trừ giờ vì đi muộn là **quyết định về lương**, không phải việc của cái đồng hồ. Trộn hai thứ vào một con số thì không ai lần lại được nữa: nhìn "285 phút" không biết là làm ít hay bị phạt.

#### Vắng mặt ≠ đi muộn

Ngày không có phiên làm việc nào trả về `late_minutes = 0`, không phải "muộn 9 tiếng". Gộp hai thứ lại thì người nghỉ phép hiện thành người đi muộn kỷ lục, và cả cột mất tin cậy.

#### Bảng riêng, không phải một loại nghỉ phép

`leave_requests` đo bằng **NGÀY** — `start_date` và `end_date` đều là cột DATE. Đơn đi muộn đo bằng **GIỜ**: *"mai tôi tới lúc 9h30"*. Nhét vào bảng kia thì phải thêm cột giờ mà 99% số dòng để trống, và mọi truy vấn khoảng ngày phải thêm một nhánh "trừ loại này ra".

Nhưng nó vẫn nằm trong **miền Leave**: cùng vòng đời `LeaveStatus`, cùng quyền `leave.approve`, cùng người duyệt, cùng màn hình. Miền Attendance chỉ *đọc* kết quả — ghép ở tầng Http như mọi chỗ nối hai miền khác.

#### Đơn chỉ bao tới đúng giờ đã xin

Xin đến 9h mà 11h mới tới thì **phần vượt quá vẫn tính muộn**.

Bỏ luật này thì một đơn duy nhất biến thành giấy thông hành cho cả ngày, và cả cơ chế duyệt mất ý nghĩa — ai cũng nộp một đơn "9h" rồi đến lúc nào cũng được.

#### Dấu đi muộn không biến mất khi được duyệt, chỉ đổi màu

Đỏ = đi muộn, xám = đi muộn có phép. Xoá hẳn dấu thì bảng công **nói dối**: người xin phép đàng hoàng và người đúng giờ trông y hệt nhau, và không ai tra lại được "hôm đó đến lúc mấy giờ". Sự thật ở lại, chỉ được giải thích thêm.

Ô tóm tắt "ngày đi muộn" thì **chỉ đếm ngày không có đơn** — một chỉ số không bao giờ về 0 là chỉ số người ta ngừng đọc.

#### Bẫy múi giờ, và test viết riêng cho nó

`work_sessions.started_at` là **UTC**, còn 8h15 là **giờ Việt Nam**. Quên quy đổi thì lệch đúng 7 tiếng — và lệch theo hướng tệ nhất: con số ra vẫn trông hợp lý. `08:20 UTC` so thẳng với `08:15` ra "muộn 5 phút", trong khi thật ra là **muộn 425 phút**.

Có một test khoá riêng đúng tình huống này. Helper test `coGioLamTu()` nhận **giờ Việt Nam** thay vì UTC, để người đọc test không phải tự cộng bảy tiếng trong đầu.

#### Chưa làm

Không có **về sớm**, không có **muộn buổi chiều** (sau giờ nghỉ trưa), không có ca theo phòng ban. Bảng `work_shifts` vẫn hoãn: cả công ty đang chung một ca, và dựng sẵn cơ chế ca theo phòng / theo người / có hiệu lực từ ngày nào là dựng một cỗ máy cho bài toán chưa tồn tại. Khi nào có phòng thật sự làm giờ khác thì `WorkShift::fromConfig()` là chỗ duy nhất phải sửa.

---

### Đóng gói và triển khai ✅ Đã chạy thử thật

Trước đợt này, quy trình deploy **chưa từng được chạy một lần nào**. Dựng thử cả stack production ở máy phát hiện **sáu lỗi**, và không lỗi nào lộ ra nếu chỉ đọc file cấu hình.

#### Sáu lỗi, theo đúng thứ tự chúng chặn

| # | Lỗi | Vì sao không ai biết |
|---|---|---|
| 1 | Không có Dockerfile cho frontend, prod compose không có dịch vụ frontend | Deploy xong được một API hoàn hảo mà **không ai vào được bằng trình duyệt** |
| 2 | Image backend **không build được**: `invalid file request backend/public/storage` | Symlink trỏ tới đường dẫn tuyệt đối trong container; Docker từ chối build context chứa nó |
| 3 | `composer install` chết: thiếu `ext-pcntl`, `ext-exif` | Image `composer:2` để CÀI không có hai extension đó, dù image chạy thật có đủ |
| 4 | Laravel chết lúc khởi động: `Class "Dedoc\Scramble\ScrambleServiceProvider" not found` | `bootstrap/cache/packages.php` sinh ra ở máy dev bị copy vào image build với `--no-dev` |
| 5 | `Invalid route action: [App\Http\Controllers\...]` | Tầng vendor chỉ copy `composer.json`, nên **classmap không có class nào của ứng dụng** — mà `--classmap-authoritative` cấm autoloader dò hệ tệp |
| 6 | `entrypoint.prod.sh` không chạy `storage:link` | Ảnh và logo trả về 404, trong khi deploy vẫn báo thành công |

Lỗi số 5 là loại tệ nhất: câu lỗi nói về route, còn nguyên nhân nằm ở một cờ của Composer cách đó ba tầng build.

#### Một origin, không phải hai tên miền

nginx đứng trước cả hai: `/api`, `/sanctum`, `/storage` sang PHP; còn lại sang Next.js.

Không phải chuyện gọn gàng — xác thực dùng **cookie phiên của Sanctum**, mà cookie gắn với origin. Tách hai tên miền là phải cấu hình CORS, `SameSite=None`, và `SANCTUM_STATEFUL_DOMAINS` khớp tuyệt đối. Ba chỗ, và sai chỗ nào cũng ra **cùng một triệu chứng**: đăng nhập xong bị đá về trang đăng nhập.

Cùng một origin thì cả ba biến mất.

#### Ảnh build ra chạy được ở mọi tên miền

`NEXT_PUBLIC_API_URL` mặc định là đường dẫn **tương đối** (`/api/v1`). Biến `NEXT_PUBLIC_*` bị nhúng vào mã lúc build, nên nhúng một địa chỉ tuyệt đối là tự trói ảnh vào đúng một tên miền — đổi tên miền phải build lại.

#### nginx không mount thư mục `public`

Volume có tên chỉ được nạp từ image **đúng một lần lúc còn rỗng**. Mount `public/` qua volume dùng chung thì deploy lần thứ hai mà `public/index.php` đổi, volume vẫn giữ bản cũ — im lặng.

Bỏ hẳn phụ thuộc đó: nginx chuyển tiếp sang php-fpm với đường dẫn script cố định, còn php-fpm tự phân giải trong container của nó. Tệp tĩnh dùng `alias` thẳng vào volume `storage`, không qua symlink.

#### Logo là ngoại lệ có chủ ý của quy tắc "ép tải xuống"

Khối `/storage/` gắn `Content-Disposition: attachment` cho mọi tệp — đúng, vì tệp người dùng tải lên không được hiện inline trên tên miền của mình.

Logo phải khác: nó là tài sản thương hiệu, chỉ quản trị viên và giám đốc tải lên được, và nó **bắt buộc** hiện trong thẻ `<img>`. Nên `/storage/branding/` có khối riêng, vẫn giữ `nosniff`. Đã đo bằng `curl`: logo không có `Content-Disposition`, tệp đính kèm khác thì có.

#### Deploy kiểm CẢ HAI trước khi báo thành công

Health check cũ chỉ gọi `/api/v1/health`. Chỉ kiểm API thì container frontend chết vẫn báo deploy xanh, và người dùng mở trình duyệt ra thấy 502. Giờ kiểm cả `/login`.

#### Đo được sau khi dựng thật

| | |
|---|---|
| Image frontend | **79 MB** (`output: standalone`, chạy bằng user thường, không lộ `X-Powered-By`) |
| `/api/v1/health` · `/login` · `/api/v1/site` | 200 |
| `/` | 307 → `/login`, đúng |
| Cookie `XSRF-TOKEN` + `explus_session` | có, `httponly`, `samesite=lax` |
| Tệp tĩnh Next | `max-age=31536000, immutable`, **một** dòng Cache-Control |

Dòng cuối cũng là một lỗi bắt được lúc đo: nginx `add_header` *thêm vào* chứ không ghi đè, nên Cache-Control ra **hai dòng**. Lần này hai dòng trùng giá trị nên vô hại, nhưng ngày Next đổi chính sách cache thì hai dòng sẽ khác nhau và cái nào thắng là chuyện may rủi. Đã bỏ dòng thừa.

#### Còn lại trước khi lên thật

- **Tên miền**: đặt `SESSION_SECURE_COOKIE=true`, `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` theo tên miền thật; đặt HTTPS phía trước (nginx đã đọc `X-Forwarded-Proto`)
- **Cảnh báo lỗi**: chưa có. Hiện lỗi production chỉ nằm trong file log trên máy chủ — nghĩa là biết hệ thống hỏng khi nhân viên nhắn tin báo

---

### Cài đặt trang ✅ Đã xong

Giám đốc tự đổi tên công ty, logo, và **các mốc chính sách** — không cần gọi lập trình viên.

Trước đó mười hai giá trị chỉ nằm trong `.env` trên máy chủ: ca làm, ân hạn đi muộn, giờ nhắc báo cáo, cửa sổ nộp đơn nghỉ. Hôm công ty chốt ca 8h15, việc "đổi giờ làm" là sửa file rồi khởi động lại container. Đó không phải thứ kỹ thuật — đó là chính sách nhân sự.

#### Thiết kế: ghi đè config, KHÔNG sửa mã miền

Điểm quyết định của cả tính năng. Cài đặt trong database được nạp vào `Config` lúc khởi động, nên `WorkShift::fromConfig()` và mọi chỗ đọc config khác **không phải sửa một dòng nào** — và toàn bộ test đã có vẫn đúng.

```
site_settings (DB) ──[boot]──▶ Config ──▶ WorkShift, LeaveWindow, ReportWindow…
```

Cách khác là để mỗi chỗ tự hỏi database. Cách đó buộc phải sửa mọi lớp đang đọc config, và mỗi lớp lại thêm một truy vấn.

#### Key/value, không phải mỗi cài đặt một cột

Thêm một cài đặt mới chỉ cần thêm một `case` vào `SettingKey` — **không cần migration**. Với dự án này đó là lý do rất thật chứ không phải sở thích: bộ quét migration của Larastan nằm sát ngưỡng, mỗi migration mới là một lần đánh cược.

Cái giá thường thấy của key/value là mất kiểu dữ liệu. Ở đây trả bằng hai enum: `SettingKey` (khoá không gõ bừa được) và `SettingType` (`get()` luôn trả đúng `int`/`bool`/`string`). Kiểu thuộc về **mã**, không có cột `type` trong bảng — hai nguồn sự thật là hai nguồn sẽ lệch nhau.

#### Dòng vắng mặt nghĩa là "dùng mặc định"

Xoá dòng là quay về mặc định trong config, không phải đặt về `null`. Và form **chỉ gửi trường đã sửa**: gửi hết thì mỗi lần bấm Lưu là ghi cứng cả mười hai dòng, kể cả những dòng chưa ai chạm — sau đó đổi mặc định trong config sẽ không còn tác dụng, và không ai hiểu vì sao.

#### Ca làm vô lý bị chặn, vì nó hỏng im lặng

Tan làm trước giờ vào làm **không làm hệ thống báo lỗi** — nó chỉ khiến mọi phép tính giờ ra số âm hoặc số 0, cho tới khi có người thắc mắc "sao tháng này ai cũng 0 giờ".

Nên bốn mốc phải tăng dần, và phép kiểm chạy trên giá trị **sau khi trộn** với cài đặt hiện tại: sửa mỗi `shift_end` thành 07:00 mà giữ nguyên giờ vào làm 08:15 vẫn là ca vô lý, mà một form chỉ gửi một trường thì không tự phát hiện được.

Mọi con số cũng có mốc trên, không chỉ `min:0` — `leave_max_days` để 100000 thì một đơn gõ nhầm năm sẽ miễn chấm công cho hai thế kỷ.

#### Logo đi ổ công khai, không đi R2

Logo hiện trên **trang đăng nhập** — trước khi có ai xác thực. R2 của dự án là bucket riêng tư, mọi đường dẫn đều ký hạn 30 phút, mà trang đăng nhập không gọi được API cần xác thực để lấy đường dẫn đó.

Nên có một đường **công khai** `/api/v1/site`, và nó **chỉ trả nhận diện**. Trả cả chính sách ở đó là phơi giờ làm và cấu hình nội bộ cho bất kỳ ai gọi tới. Có test khoá riêng cho đúng ranh giới này.

**Không nhận SVG:** SVG là XML và chạy được script, mà tệp này được phục vụ từ chính tên miền ứng dụng — một SVG có mã nhúng là lỗ hổng XSS ngay trong trang đăng nhập.

#### Ba luật kiến trúc bắt lỗi khi làm

Đáng ghi lại, vì cả ba đều bắt đúng và bắt sớm:

| Luật | Bắt gì | Sửa thế nào |
|---|---|---|
| Preset Laravel | `SettingsServiceProvider` đặt trong `App\Support` | Chuyển sang `App\Providers` — Support giữ **cơ chế**, Providers giữ **dây nối** |
| Preset Laravel | `SiteSetting` là model nằm ngoài `App\Models` | Thêm ngoại lệ **có lý do viết ra**: cài đặt ghi đè config mà mọi miền đều đọc, nên nó phải ở tầng ai cũng được phụ thuộc vào |
| `ControllerAuthorizationTest` | `SiteBrandingController` không khai quyền | Thêm vào danh sách miễn trừ kèm lý do — nó công khai **có chủ ý** |

Luật thứ ba là luật tôi tự viết hôm trước, và nó bắt được ngay lần đầu có cơ hội.

#### Lỗ hổng deploy phát hiện khi thử

Thêm quyền `setting.manage` xong, giám đốc bấm vào trang cài đặt vẫn **403** — vì quyền mới chưa có trong database. Chạy `db:seed --class=RolePermissionSeeder` là xong.

Nhưng `scripts/deploy.sh` **không hề chạy seeder đó**, chỉ chạy `migrate`. Nghĩa là deploy lên máy chủ thật sẽ "thành công" — mã mới chạy, trang có đủ — mà không ai vào được, và không có gì trong log nói vì sao.

Đã bổ sung vào deploy. Seeder vốn đã an toàn cho hệ thống đang chạy: nó **chỉ cấp thêm quyền vừa mới ra đời**, không dùng `syncPermissions` nên không xoá tuỳ chỉnh của quản trị viên.

---

### Dòng thời gian một ngày ✅ Đã xong

Màn hình cho người quản lý mở mỗi sáng: **hôm nay ai đang làm, và khoảng nào ngồi không**.

Lưới tháng trả lời *"tháng này ai làm bao nhiêu giờ"*. Câu đó không nói được nhịp làm việc — làm 5 tiếng liền một mạch và làm 5 tiếng rải rác từ sáng tới tối cho ra **cùng một con số**. Mà nhịp mới là thứ nhìn vào để thấy hôm nay có gì bất thường.

#### Dữ liệu đã có sẵn, không phải thêm gì

Nhịp tim cách nhau quá **10 phút** thì `RecordHeartbeatAction` cắt thành phiên mới. Nên **khe giữa hai phiên chính là lúc không có tương tác nào** — thông tin vốn đã nằm trong `work_sessions`, chỉ là chưa ai vẽ nó ra.

Mỗi ngày mỗi người có 3–6 phiên, nên màn này không sinh thêm truy vấn nặng: một câu SQL cho cả đội, gom trong PHP.

#### Ba màu, ba ý nghĩa

| Màu | Nghĩa |
|---|---|
| Sắc miền (xanh ngọc) | Có tương tác thật |
| **Vàng** | **Ngồi không** — khoảng lặng giữa hai phiên |
| Xám nhạt | Nghỉ trưa theo ca |
| Xám | Ngoài khoảng đã ghi nhận: chưa vào, hoặc đã về |

#### Nghỉ trưa KHÔNG phải ngồi không

Bản đầu tính cả giờ nghỉ trưa vào "ngồi không". Chạy thử với dữ liệu thật thì lộ ra ngay: một người làm bình thường vẫn bị gắn **95 phút ngồi không** chỉ vì đã đi ăn trưa.

Để nguyên thì **ngày nào cũng có một khoảng vàng 90 phút cho mọi người** — và cờ bật cho tất cả là cờ vô nghĩa. Cùng nguyên tắc đã ghi ở ô tóm tắt "ngày đi muộn": một chỉ số không bao giờ về 0 là chỉ số người ta ngừng đọc.

Đã tách thành `lunch_minutes` riêng. Khe vẫn được **vẽ nguyên vẹn** trên giao diện — người xem cần thấy đúng khoảng trống có thật — chỉ có phép **đếm** là tách đôi:

```
khe 12:00–13:35 = 95 phút  →  nghỉ trưa 90 phút + ngồi không 5 phút
```

#### Khoảng trước phiên đầu và sau phiên cuối không tính

Chưa tới giờ làm và đã về thì không phải "ngồi không". Gộp vào là biến cả buổi tối thành thời gian lười biếng, và con số mất sạch ý nghĩa.

#### Khung giờ do dữ liệu quyết, không cắt cứng

Mặc định phủ 08h–18h, **tự nới ra** khi có người làm ngoài ca. Công ty làm remote nên làm buổi tối là bình thường; cắt cứng thì phiên lúc 21h **biến mất khỏi màn hình** mà không có gì báo — đúng loại hỏng im lặng dự án này liên tục phải trả giá. Có test khoá riêng.

#### Người vắng mặt vẫn hiện

Lọc họ ra thì màn hình toàn người đang làm, và người mở màn này không thấy được ai chưa vào — mà đó mới là thông tin họ cần nhất. Hàng của người chưa có hoạt động bị làm mờ để mắt lướt qua bắt được ngay.

#### Chưa có địa chỉ IP

Mẫu tham khảo có hiện IP. Bảng `work_sessions` **không lưu IP**, nên muốn có phải thêm cột — và đó là một quyết định chứ không phải một dòng mã: IP là dữ liệu cá nhân theo Nghị định 13, mà với nhân sự làm từ xa nó cũng chỉ nói được "người này đang ở nhà". Để ngỏ cho tới khi có nhu cầu thật.

---

#### Bật R2, và một lỗi bắt được vì chạy thật

Ngày **24/08/2026** chuyển `MEDIA_DISK` từ `public` sang `r2`. Lúc chuyển hệ thống có **0 tệp đính kèm**, nên không phải di chuyển gì.

##### Token phải là "Object Read & Write"

Token cấp lần đầu chỉ có **Object Read only**: kết nối tốt, `listObjects` tốt, nhưng `PutObject` bị từ chối. Không sửa được bằng cấu hình phía ứng dụng — phải cấp lại token trên Cloudflare.

Token mới giới hạn vào **đúng một bucket** (`explus-media`) thay vì "all buckets": key lộ thì kẻ lấy được cũng chỉ đụng được đúng chỗ đó.

##### `R2_PUBLIC_URL=` bỏ trống suýt biến bucket riêng tư thành công khai

Bật xong, thử tải một tệp lên rồi lấy đường dẫn — ra `/3/thu-r2.txt`. Một đường dẫn **tương đối**, hỏng với mọi người xem.

Nguyên nhân: dòng `R2_PUBLIC_URL=` bỏ trống trong `.env` cho ra **chuỗi rỗng**, không phải `null`. Phép kiểm trong `MediaUrl` là:

```php
return Config::get("filesystems.disks.{$disk}.url") !== null;   // ❌
```

Chuỗi rỗng `!== null` là **true**, nên cả hệ thống kết luận bucket riêng tư kia là công khai và thôi ký đường dẫn.

Hỏng im lặng đúng kiểu tệ nhất: tệp vẫn tải lên được, hàm vẫn trả về một chuỗi, không ngoại lệ nào, test cũ vẫn xanh. Chỉ là **không ai xem được ảnh** — và chỉ lộ ra khi có người bấm vào.

Sửa ở tầng mã chứ không phải ở `.env`, vì một dòng env bỏ trống là thứ quá dễ xảy ra để phụ thuộc vào việc người cấu hình nhớ xoá hẳn dòng đó:

```php
$url = Config::get("filesystems.disks.{$disk}.url");

return is_string($url) && trim($url) !== '';                    // ✅
```

##### Vì sao KHÔNG bật Public Development URL

Để `R2_PUBLIC_URL` trống là lựa chọn có chủ ý. Bật địa chỉ công khai của Cloudflare thì mọi tệp trong bucket thành **ai có đường dẫn cũng xem được**, không cần đăng nhập — ảnh báo cáo ngày, ảnh chụp màn hình có thể chứa dữ liệu khách hàng, đều nằm ngoài hàng rào đăng nhập.

Để trống thì mỗi lần xem được ký một đường dẫn hạn **30 phút**. Hết hạn là link chết.

##### Kiểm bằng cách chạy thật, không chỉ bằng test

| Bước | Kết quả |
|---|---|
| Ghi / đọc lại / xoá trực tiếp trên ổ `r2` | OK |
| Tải tệp qua Media Library | ghi vào ổ `r2` |
| Đường dẫn sinh ra | có `X-Amz-Signature`, trỏ về host R2 |
| `GET` đường dẫn đó | `HTTP/1.1 200 OK`, nội dung khớp |

Chính bước cuối mới lộ ra lỗi chuỗi rỗng — bốn bước đầu đều "xanh" trong khi đường dẫn đã hỏng.

---

#### Thông báo cho đơn đi muộn

Cùng khuôn với đơn nghỉ, và vì đúng những lý do đó:

- **Nộp đơn** → báo cho **quản lý trực tiếp** (`manager_id`), không báo cho mọi người có quyền duyệt. Bắn cho cả nhóm thì bốn người cùng nhận một đơn, ba người trong đó không liên quan.
- **Duyệt hoặc từ chối** → báo cho người nộp.

Trường hợp quan trọng nhất là **bị từ chối**: không báo thì người ta đinh ninh mình đã xin phép xong rồi cứ thế đi muộn, hôm sau mới biết ngày đó vẫn bị đánh dấu. Nên lý do từ chối nằm thẳng trong nội dung thông báo, không bắt mở trang mới đọc được.

Thông báo duyệt cũng nhắc lại mốc giờ — *"Ngày 17/09 bạn được đến muộn, tới 09:45. Đến sau mốc đó vẫn tính là đi muộn."* — vì đó là thứ người nhận cần nhớ nhất.

Người **không có quản lý trực tiếp** thì không ai được báo. Đó là lưới hứng có chủ ý, và là lý do hộp duyệt phải hiện số đơn đang chờ.

---

#### Không có gì bắt controller mới phải khai quyền

Hôm nay 33/54 controller có chặn quyền, 21 cái còn lại đều có lý do đúng. Nhưng **không có gì giữ cho điều đó còn đúng vào tháng sau**.

Ai thêm `DeleteProjectController` mà quên `#[Authorize]` thì Deptrac không bắt (nó chỉ theo dõi phụ thuộc giữa các tầng), Larastan không bắt (thiếu một attribute không phải lỗi kiểu), và test cũng không bắt — không ai viết test cho quyền mình không biết là thiếu. Nó chỉ lộ ra khi có người thử.

`tests/Architecture/ControllerAuthorizationTest.php` đóng chỗ đó bằng năm luật:

| Luật | Bắt được gì |
|---|---|
| Mọi controller phải có dấu hiệu kiểm quyền, **hoặc** nằm trong danh sách miễn trừ | Controller mới quên khai quyền |
| Danh sách miễn trừ không có dòng chết | Dòng trỏ tới tệp đã xoá, để lần sau ai tạo trùng tên được miễn mà không ai để ý |
| Controller đã tự kiểm quyền thì không được nằm trong danh sách miễn trừ | Miễn trừ thừa — nó nói dối rằng chỗ đó không cần quyền |
| Mọi lý do miễn trừ phải dài trên 20 ký tự | Những dòng "không cần" vô nghĩa |
| Controller "của chính tôi" không được nhận `user_id` từ client | **Lỗ hổng đọc dữ liệu người khác** |

Luật cuối là đáng giá nhất. Mười controller được miễn kiểm quyền **chỉ vì** chúng luôn thao tác trên `$request->user()`. Ngày nào có người thêm `?user_id=` cho tiện thì cả nhóm biến thành lỗ hổng — mà danh sách miễn trừ vẫn nói rằng chúng an toàn.

**Miễn trừ vẫn được phép** — nhiều controller thật sự không cần quyền. Nhưng nó phải là một hành động cố ý, có người gõ ra lý do, chứ không phải hệ quả của việc quên.

Cả hai lưới đã được kiểm bằng cách **cố tình phá**: thêm một controller không khai quyền, và thêm `?user_id=` vào `MyReportsController`. Cả hai đều đỏ với câu lỗi nói rõ phải làm gì.

*Luật này không chặn được gì.* Chặn thật nằm ở Policy và `#[Authorize]`; đây là lưới hứng cho thứ bị bỏ quên.

---

### Larastan sập khi thêm migration ✅ Đã vá, nhưng chưa khỏi hẳn

Ngày **19/08/2026**, thêm một migration làm Larastan đỏ **717 lỗi**. Không phải lỗi mã nguồn.

#### Triệu chứng và bằng chứng

Larastan bỏ mất schema của tám migration đầu tiên — `users`, `departments`, `positions`, `teams`, `login_attempts`, `two_factor_codes`, `task_labels`, `task_due_date_changes` — nên mọi chỗ đọc `$user->name` đều báo `property.notFound`.

Hỏng **im lặng**: `MigrationHelper` có một chỗ `catch (ParserErrorsException) { continue; }` — file nào không đọc được thì bỏ qua, không cảnh báo gì.

| Thử nghiệm | Kết quả |
|---|---|
| Migration của tính năng đi muộn | 717 lỗi |
| Thay bằng migration **không liên quan** (`widget_configuration_items`) | **717 lỗi** |
| Migration **rỗng hoàn toàn** (`up()` không làm gì) | **717 lỗi** |
| Xoá bớt **bất kỳ** migration cũ nào | ✅ sạch |
| Đổi tên file ngắn lại | ✅ sạch |

Đã loại trừ: nội dung migration, bộ nhớ (`--memory-limit=4G`), chạy song song (`--debug`), cache (`enableMigrationCache` vốn đã tắt), trạng thái database, phiên bản Larastan (3.10.0 — mới nhất). Kết quả phụ thuộc **tổng độ dài tên các file migration**, không phải số lượng file. Cơ chế chính xác **chưa xác định được**.

#### Cách vá: trả lại quy tắc mà chính dự án đã đặt ra

Tám model mất schema **chính là tám model thiếu khối `@property`**:

| Model | `@property` trước khi vá |
|---|---|
| `Department`, `Position`, `Team`, `LoginAttempt`, `TaskLabel`, `TaskDueDateChange` | **0 dòng** |
| `TwoFactorCode` | 3 dòng |
| `User` | 6 dòng |

Không phải trùng hợp. README đã ghi từ đầu: *"Khối `@property` trên model — **bắt buộc**, liệt kê đủ mọi cột"*. Tám chỗ này không theo, nên chúng phải nhờ Larastan suy từ migration — và khi bộ quét hỏng thì chúng mất trắng.

Khai đủ `@property` cho cả tám: **717 → 16 lỗi**. Riêng `Department` gỡ 85 lỗi.

Đây là cách vá đúng chứ không phải mẹo: nó đóng đúng chỗ dự án tự đặt luật rồi không theo, và làm mã đọc dễ hơn cho người — không ai còn phải mở migration để biết `$phong->is_active` trả về `bool` hay `int`.

#### 16 lỗi còn lại, và vì sao phải rút ngắn tên file

16 lỗi cuối đều là **tên cột có tiền tố bảng** — `'users.id'`, `'tasks.status'`, `'tasks.due_date'` — cộng vài thuộc tính ảo của `JsonResource`.

Loại này Larastan kiểm bằng **schema**, không bằng docblock. Đã thử thêm `@param Builder<Task>` cho closure: **không có tác dụng**. Đã thử `disableMigrationScan: true`: vẫn đúng 16 lỗi. Chúng bắt buộc cần bộ quét chạy được.

Nên file migration được đặt tên ngắn — `2026_08_19_000000_late_arrivals.php` thay vì `..._create_late_arrival_requests_table.php` — để tổng độ dài lùi xuống dưới ngưỡng. Laravel không quan tâm tên file ngoài thứ tự sắp xếp; tên bảng nằm trong `Schema::create()`.

#### ⚠️ Món nợ chưa trả

**Đây là cách né, không phải cách chữa.** Dự án vẫn nằm sát mép: migration tiếp theo có tên đủ dài sẽ lại làm đỏ.

Blast radius đã nhỏ đi rất nhiều nhờ các khối `@property` (717 → 16), nhưng chưa về 0. Ba hướng chữa dứt điểm, **chưa chọn**:

1. **Gộp schema** (`schema:dump`) — hướng chính thống của Laravel cho dự án nhiều migration, Larastan hỗ trợ qua `SquashedMigrationHelper`. Đổi cách deploy và cách test dựng lại DB. *(Đã thử: chạy trong container `app` cho ra file 0 byte vì container PHP không có `mysqldump` — phải chạy từ container `mysql`.)*
2. **Khai `@property` cho nốt các model còn lại** rồi tìm cách bỏ hẳn phụ thuộc vào schema — nhưng tên cột có tiền tố bảng thì không có docblock nào thay được.
3. **Báo lỗi ngược lên Larastan** — dài hạn, và là việc nên làm vì lỗi im lặng kiểu này ai cũng có thể dính.

**Quy ước mới cho tới khi chữa xong:** đặt tên file migration **ngắn** (bỏ tiền tố `create_` và hậu tố `_table`), và nếu Larastan đột nhiên đỏ hàng trăm lỗi `property.notFound` thì đọc mục này trước khi đi sửa mã.

---

### Đợt 4 — Phần còn lại: quỹ phép, OT, chốt kỳ công

- [ ] `leave_balances` — quỹ phép năm, phép tồn năm trước, phép ứng trước
- [ ] `overtime_requests` — đăng ký OT, duyệt trước mới được tính
- [ ] Hệ số OT theo luật: ngày thường 150%, ngày nghỉ 200%, ngày lễ 300%
- [ ] **Đơn giải trình & điều chỉnh công** — quên bấm giờ, mất mạng, họp ngoài. Nhân viên giải trình → leader duyệt → sửa công, giữ nguyên vết cũ
- [ ] **Chốt kỳ công** — khoá sổ theo tháng, sau đó không ai sửa được kể cả admin trừ khi mở khoá có ghi lý do
- [ ] **Nhật ký kiểm toán bất biến** — ai sửa công của ai, giá trị cũ, lý do
- [ ] Tính khấu trừ theo giờ công thực tế (xem mục dưới)

#### Cách tính lương theo giờ công — lưu ý pháp lý

Công ty chọn phương án **tính lương theo ngày công thực tế, thiếu giờ trừ theo tỉ lệ lương ngày**.

Bộ luật Lao động 2019 Điều 127 nghiêm cấm *"phạt tiền, cắt lương thay việc xử lý kỷ luật lao động"*. Nhưng **trả lương theo thời gian làm việc thực tế** thì hoàn toàn hợp lệ — và đó đúng là cách công ty đang muốn làm. Khác biệt nằm ở cách đặt tên và cách ghi trên phiếu lương:

| Cách làm | Đánh giá |
|---|---|
| ❌ "Đi muộn 15 phút → phạt 100.000đ" | Phạt tiền cố định — trái luật |
| ✅ "Làm 7.5/8 giờ → trả lương 7.5 giờ" | Trả theo giờ công thực tế — hợp pháp |

**Quy ước trong mã nguồn:** không đặt tên bảng/cột là `fine` hay `penalty` cho phần này. Dùng `worked_hours`, `payable_hours`, `hours_shortfall`. Trên phiếu lương ghi *"lương theo giờ công thực tế"*.

Công thức, **ba tham số dưới đây phải nằm trong bảng cấu hình, không nằm trong code**:

```
Lương giờ = Lương tháng / Số ngày công chuẩn / Số giờ mỗi ca
Khấu trừ  = (Số phút thiếu - Số phút ân hạn) / 60 × Lương giờ
```

- [ ] Cấu hình **số ngày công chuẩn** — cố định 26 ngày hay theo lịch thực tế từng tháng?
- [ ] Cấu hình **phút ân hạn** — trễ dưới bao nhiêu phút thì bỏ qua?
- [ ] Cấu hình **quy tắc làm tròn** — tính đúng số phút, hay làm tròn lên block 15/30 phút?

### Mức lương ✅ Phần đặt và xem đã xong

Làm sớm hơn lộ trình, theo yêu cầu. Đây mới là **mức lương** (đặt và xem), chưa phải bảng lương tính ra tiền phải trả — phần đó cần chốt kỳ công ở đợt 4 và định dạng xuất cho kế toán.

| Trước | Sau |
|---|---|
| Không có bảng, không có cột, không có `Domain/Payroll` | `salary_records` + `payroll_audits`, miền riêng |
| Lương nằm ngoài hệ thống, ở file của kế toán | `/payroll` — bảng lương, lịch sử, đặt mức mới |

- [x] Ba quyền riêng: `payroll.view.own` · `payroll.view.all` · `payroll.manage`
- [x] Lịch sử mức lương theo khoảng hiệu lực, chỉ ghi thêm
- [x] Nhân viên xem được mức và lịch sử của chính mình
- [x] Nhật ký **cả việc xem lẫn việc sửa**
- [ ] Bảng lương tính ra tiền phải trả — chờ chốt kỳ công (đợt 4)
- [ ] Xuất cho kế toán — chờ biết dùng phần mềm gì

#### Không nằm trên bảng `users`

Đây là quyết định quan trọng nhất của phần này. `users` chảy ra ngoài qua `UserResource` ở `/auth/me`, `/users`, `/users/assignable`, và lồng trong mọi task có người thực hiện. Một cột `salary` ở đó sẽ lọt ra qua bất kỳ `toArray()` nào — một `dd()` lúc gỡ lỗi, một payload job vào Redis, một dòng log của thư viện bên thứ ba. Dự án đã dính đúng họ lỗi này với `two_factor_secret` ở mục 1.9 và phải chặn bằng `#[Hidden]`; với lương thì lớp phòng vệ đó quá mỏng.

Bảng riêng nghĩa là **phải cố ý truy vấn mới đọc được**. Có test khoá lại: gọi ba endpoint nhân sự rồi khẳng định chuỗi lương không xuất hiện ở đâu trong phản hồi. Nếu sau này ai đó "cho tiện" thêm cột lương vào `users`, test đỏ ngay.

Hệ quả kiến trúc: `User` thuộc miền Identity, mà Identity **không được tham chiếu tới Payroll** (deptrac chặn, và luật đó đúng — Identity là shared kernel). Nên không có quan hệ `User::salaryRecords()`. Tầng Http là nơi duy nhất biết cả hai miền, và nó gắn mức lương vào từng `User` bằng `setRelation` sau khi đã gom trong một truy vấn.

#### Vì sao cột lương không mã hoá ở tầng ứng dụng

Mục 1.9 từng ghi ràng buộc "cột lương dùng `encrypted` cast", rồi phần Mức lương được làm sớm hơn lộ trình và **ràng buộc đó bị bỏ qua mà không ai nhận ra** — ghi chú cũ vẫn nói "đợt 1 chưa có cột lương nào" trong khi cột đã tồn tại. Đây là quyết định chốt lại sau khi rà.

**Chốt: giữ `DECIMAL(15,2)` ở tầng ứng dụng, mã hoá ở tầng lưu trữ khi triển khai.**

Mã hoá ở tầng ứng dụng có đúng **một** điểm mạnh mà cách kia không có: người có quyền đọc thẳng database sẽ chỉ thấy chuỗi nhị phân. Điểm mạnh đó thật, nhưng mỏng hơn vẻ ngoài — ứng dụng phải giải mã được, nên `APP_KEY` nằm trên máy chủ ứng dụng, và ở một công ty cỡ này thì ai có quyền vào database thường cũng có quyền vào máy chủ đó.

Đổi lại là ba cái giá:

| Cái giá | Mức độ thật |
|---|---|
| Mất `APP_KEY` = **mất vĩnh viễn toàn bộ lịch sử lương** | Nghiêm trọng. `php artisan key:generate` chạy nhầm khi deploy là tai nạn có thật, và dự án **chưa có staging, chưa từng diễn tập phục hồi backup** — hai việc còn treo ở mục 1.10 |
| Xoay khoá trở thành một cuộc migrate dữ liệu | Đáng kể, và không có công cụ sẵn |
| Mất `ORDER BY`, `SUM`, `WHERE >` trên cột lương | **Nhỏ** ở quy mô 200 người — sắp xếp và cộng trong PHP là đủ |

Cái giá thứ ba là lý do tôi từng viện dẫn, và nó hoá ra là cái nhẹ nhất. Hai cái đầu mới là thứ chặn: **thêm một cách mất dữ liệu vĩnh viễn vào một hệ thống chưa từng thử phục hồi backup lần nào là đổi một rủi ro rò rỉ lấy một rủi ro mất trắng.**

Mã hoá tầng lưu trữ (mã hoá đĩa, hoặc TDE của MySQL) chặn đúng kịch bản nguy hiểm nhất trong thực tế — **bản backup bị lộ, ổ đĩa bị lấy** — mà không thêm đường nào làm mất dữ liệu.

##### Rủi ro còn lại, ghi ra để chấp nhận có ý thức

**Người có quyền đọc thẳng database vẫn đọc được lương của mọi người.** Cách này không chặn điều đó. Ba lớp bù lại, và cả ba đã kiểm chứng là chạy thật chứ không phải chỉ có trong tài liệu:

1. **Bảng riêng** — `salary_records` tách khỏi `users`, nên phải cố ý truy vấn mới đọc được. Có test đọc phản hồi thô của ba endpoint nhân sự rồi khẳng định chuỗi `salary` không xuất hiện ở đâu.
2. **Ba quyền riêng** — `payroll.view.own` · `payroll.view.all` · `payroll.manage`, không dùng chung với quyền quản trị nhân sự.
3. **Nhật ký ghi cả lượt XEM** — `payroll_audits` có `viewed_list` và `viewed_person`, không chỉ `salary_changed`. Đọc trộm qua giao diện thì để lại vết; đọc thẳng database thì không.

Nếu công ty đánh giá rủi ro "người có quyền database" là không chấp nhận được, thì hướng đúng **không phải** là `encrypted` cast — mà là **thu hẹp danh sách người có quyền vào database production**, và bật kiểm toán ở tầng MySQL. Mã hoá ứng dụng chỉ dời chỗ cất chìa khoá chứ không đổi được ai đang cầm nó.

#### Lương là lịch sử, không phải một con số

Cái bẫy mà nhiều hệ thống mắc: lưu `salary` như giá trị hiện hành. Rồi tháng 6 tăng lương, tháng 7 kế toán tính lại bảng lương tháng 3 → ra số sai, vì hệ thống chỉ còn biết mức mới.

```
salary_records
  user_id · effective_from · effective_to (null = đang hiệu lực)
  base_salary · allowance · currency · reason · created_by
```

Tăng lương là **thêm** một dòng và **đóng** dòng cũ vào hôm trước ngày mức mới bắt đầu — không trừ một ngày thì có đúng một ngày hai mức cùng hiệu lực. Cả hai thao tác nằm trong một giao dịch kèm `lockForUpdate`: đóng xong mà mở hỏng thì nhân viên không còn mức lương nào, và đó là loại hỏng không ai phát hiện cho tới kỳ trả lương.

Ghi lùi ngày **được phép** miễn là sau ngày bắt đầu của mức hiện hành — nhân sự nhập muộn vài ngày là chuyện bình thường. Sớm hơn thì `SALARY_PERIOD_OVERLAP`.

#### Tiền là chuỗi, từ database ra tới giao diện

`DECIMAL(15,2)` ở database, cast `decimal:2` trả chuỗi, Resource trả chuỗi, TypeScript khai `type Money = string`. Không có chỗ nào tiền đi qua `float` hay `number`.

Cộng bằng `bcadd` chứ không bằng `+`: `12500000.10 + 2000000.20` trong PHP float ra `14500000.299999999`. Đã kiểm chứng bằng cách gọi thật qua HTTP — kết quả trả về đúng `14500000.30`.

Validate dùng `decimal:0,2` chứ không `numeric`: `numeric` nhận cả `1.0e7` lẫn `0x1A` rồi ép sang float ở tầng dưới. Có trần một tỉ đồng mỗi tháng, không phải để hạn chế mà để chặn lỗi gõ thừa số 0 — 12.000.000 thành 120.000.000 thì không có gì báo.

Cột `currency` luôn có, kể cả khi công ty chỉ trả VND: một cột mặc định gần như không tốn gì, còn số tiền không có đơn vị thì tới ngày trả cho cộng tác viên nước ngoài là dữ liệu hỏng không khôi phục được.

#### Nhật ký ghi cả việc XEM

Khác mọi nhật ký khác trong hệ thống. Với lương, *"ai đã xem bảng lương phòng Kinh doanh"* là câu hỏi có thật và sẽ có người hỏi.

Ba biến cố: xem bảng lương, xem lịch sử một người, đặt mức mới. **Tự xem lương của mình thì không ghi** — nhật ký này để trả lời "ai đã xem lương người khác", ghi cả lượt tự xem thì bảng đầy rác trong một tuần và không ai đọc nữa.

**Không chứa số tiền.** Nhật ký kiểm toán mang theo dữ liệu nhạy cảm thì bản thân nó thành chỗ rò rỉ thứ hai: ai đọc được nhật ký sẽ biết lương cả công ty mà không cần quyền xem lương. Cùng nguyên tắc với nhật ký đặt lại mật khẩu ở phần nhân sự.

Bảng riêng của miền Payroll chứ không dùng `user_activities` của Identity: enum biến cố bên đó không nên phình ra vì khái niệm của miền khác.

#### Ai thấy gì

| Vai trò | Của mình | Toàn công ty | Đặt mức |
|---|---|---|---|
| Nhân viên | ✅ | ❌ | ❌ |
| Trưởng phòng | ✅ | ❌ | ❌ |
| Giám đốc | ✅ | ✅ | ✅ |
| Quản trị hệ thống | ✅ | ✅ | ✅ |

**Trưởng phòng không xem được lương cấp dưới.** Đó là quyết định chính sách của công ty, không phải mặc nhiên — và mặc định an toàn là không. Mở ra sau này chỉ cần thêm một quyền vào vai trò.

**Không tự đặt lương cho chính mình**, kể cả khi có `payroll.manage`. Cùng họ với luật chặn tự đổi vai trò và tự duyệt ngày công của mình.

Một điểm cần biết rõ chứ không phải mặc nhiên: `Role::Admin => Permission::cases()` cuốn hết mọi quyền, kể cả quyền thêm mới ở đợt sau. Với lương nghĩa là **người quản trị hệ thống — thường là IT — xem được lương cả công ty**. Ở nhiều công ty đó là điều không mong muốn. Công ty này chọn để admin quản lý lương nên giữ nguyên; muốn tách thì liệt kê quyền tường minh trong `Role::Admin` thay vì `cases()`. Đã ghi chú ngay tại chỗ.

#### Giao diện tách hẳn khỏi màn nhân sự

`/payroll` là đường dẫn riêng, **không** phải một tab trong hộp thoại sửa nhân viên. Hộp thoại đó dùng bởi người có `user.manage`, mà người đó chưa chắc có quyền xem lương — nhét chung thì component phải ẩn/hiện trường theo quyền, và đó đúng là cách rò rỉ xảy ra. Đường dẫn riêng nghĩa là guard riêng, không có nhánh `if` nào quyết định lương có hiện hay không.

Hộp thoại hiện **lịch sử trước, ô nhập sau**: người đặt lương cần thấy mức hiện tại trước khi gõ số mới. Đặt ô nhập lên đầu là mời người ta gõ mà chưa biết đang gõ đè lên cái gì.

Người chưa được đặt mức nào hiện nhãn "Chưa đặt" — trạng thái hợp lệ, không phải lỗi, và là thứ người quản lý cần thấy.

#### Kết quả kiểm thử

| Cổng | Kết quả |
|---|---|
| Pest | ✅ **428 passed** (3835 assertions) |
| Pint · Larastan mức 8 · Deptrac | ✅ Sạch |
| ESLint / Prettier / `tsc` / `next build` | ✅ PASS |

Thêm **23 test** trong `tests/Feature/Http/Payroll/SalaryTest.php`.

Đã **chạy thật qua HTTP**: đặt mức `12.500.000,10 + 2.000.000,20` → tổng ra đúng `14.500.000,30`; tăng lương → mức cũ đóng đúng 30/06, mức mới hiệu lực 01/07; nhân viên xem của mình `200`, xem của người khác `403`, xem bảng công ty `403`, admin tự đặt lương cho mình `403`; `/users` không lộ chuỗi lương nào. Nhật ký ghi đủ ba biến cố, `context` chỉ có ngày hiệu lực chứ không có số tiền. Dữ liệu và token kiểm thử đã dọn.

### Thưởng dự án ✅ Quỹ và chia thủ công đã xong

Làm sớm hơn lộ trình, theo yêu cầu. Bản đầu: **quỹ thưởng theo dự án + chia thủ công kèm lý do + màn xem của nhân viên**. Chưa có chấm điểm tự động — lý do ở cuối mục.

#### Từ "phạt" không tồn tại trong mô hình dữ liệu

**Điều 127 khoản 2 Bộ luật Lao động 2019 nghiêm cấm "phạt tiền, cắt lương thay việc xử lý kỷ luật lao động".** Không phải chuyện đặt tên cho khéo: nếu tồn tại một bản ghi mang số tiền âm trừ vào thu nhập vì làm sai thì bản chất là phạt tiền, dù cột đó tên gì.

Kỷ luật lao động vẫn có, nhưng luật chỉ cho bốn hình thức (Điều 124): khiển trách, kéo dài thời hạn nâng lương không quá 6 tháng, cách chức, sa thải. **Không hình thức nào là tiền**, nên không hình thức nào thuộc về module này.

Thứ hợp pháp và giải quyết đúng nhu cầu là **thưởng có điều kiện** (Điều 104): làm tốt thì phần chia lớn, làm kém thì phần chia nhỏ, kể cả bằng 0. Không có "trừ" ở đâu cả.

#### Ràng buộc "không bao giờ âm" nằm ở database

```sql
ALTER TABLE bonus_allocations
  ADD CONSTRAINT chk_bonus_not_negative CHECK (amount >= 0);
```

Ba lớp chặn cho cùng một luật: validate (`min:0`), Action, và ràng buộc `CHECK`. Kiểm ở tầng Action thôi thì một Action mới viết sau này có thể quên; kiểm ở database thì **mọi đường ghi đều đâm vào cùng một bức tường**, kể cả câu `UPDATE` gõ tay trong tinker.

Đã kiểm chứng bằng cách chạy thật một câu `UPDATE` bỏ qua toàn bộ ứng dụng:

```
Check constraint 'chk_bonus_not_negative' is violated
```

Đây là điều khiến hệ thống này **không thể** biến thành công cụ phạt tiền, kể cả khi ai đó về sau muốn vậy.

#### Hai bảng

```
project_bonus_pools                    bonus_allocations
  project_id (một dự án một quỹ)         pool_id · user_id
  total_amount · currency                amount ≥ 0
  status: draft → locked → distributed   reason (bắt buộc)
  condition_note                         decided_by
  locked_at · distributed_at
```

**Miền Payroll không khai quan hệ Eloquent tới `Project`**, dù `project_id` là khoá ngoại thật. deptrac chỉ cho `Payroll → Identity, Support`; tầng Http là nơi duy nhất biết cả hai miền và nó tự ghép tên dự án vào — cùng cách đã dùng cho bảng lương ghép với `User`.

**`condition_note` ghi bằng lời, không mã hoá thành luật máy chạy.** Điều kiện mở quỹ ("dự án nghiệm thu đúng hạn và khách hàng thanh toán đủ") là quyết định kinh doanh, không phải công thức.

#### Chốt là một chiều

`draft` sửa thoải mái → `locked` nhân viên xem được phần của mình, không ai sửa được nữa → `distributed` kế toán xác nhận đã trả.

Thiếu bước khoá thì con số thay đổi sau khi đã báo cho nhân viên, và đó là thứ phá niềm tin nhanh nhất. **Không có đường quay lại `draft`** — mở lại một quỹ đã chốt nghĩa là đổi được con số mà nhân viên đã nhìn thấy.

Bản nháp **không** hiện cho nhân viên: con số lúc đó còn đổi, mà đã cho xem một lần thì mọi lần đổi sau đều bị đọc thành "bị cắt bớt", kể cả khi con số tăng lên.

#### Chia là quyết định trên cả nhóm

Endpoint chia **thay thế toàn bộ danh sách**, không sửa lẻ từng người. Hai lý do: trạng thái trung gian khi sửa lẻ có thể vượt quỹ, và cập nhật từng dòng sẽ để sót người đã bị bỏ khỏi danh sách — họ vẫn nhận thưởng.

Tổng phần chia không được vượt quỹ, kiểm ở Action. Chia vượt quỹ là lỗi kế toán phát hiện sau, lúc tiền đã hứa với nhân viên rồi.

#### Minh bạch với nhân viên

Nhân viên xem được phần của mình **kèm lý do** — quỹ thưởng bí mật là nguồn nghi ngờ lớn nhất. Nhưng chỉ phần của mình, không kèm phần của người khác: đó là thu nhập của họ. Có test khoá lại điều đó bằng cách khẳng định chuỗi tiền của người kia không xuất hiện trong phản hồi.

#### Ai làm gì

| Vai trò | Thưởng của mình | Quỹ dự án | Chia thưởng |
|---|---|---|---|
| Nhân viên | ✅ | ❌ | ❌ |
| Trưởng phòng | ✅ | ✅ | ✅ |
| Giám đốc · Admin | ✅ | ✅ | ✅ |

**Trưởng phòng chia được thưởng nhưng vẫn không xem được lương của ai**, kể cả cấp dưới. Hai khoản khác nhau về bản chất nên tách quyền — gộp chung thì muốn cho trưởng phòng chia thưởng là phải mở luôn quyền xem lương cả công ty. Có test khoá lại.

#### Vì sao chưa có chấm điểm tự động

Hệ thống đã sẵn dữ liệu để chấm: `tasks.due_date` + `completed_at`, `task_due_date_changes`, `task_activities`, `work_sessions`. Nhưng **không lấy mấy con số đó nhân với tiền**.

Chỉ số nào gắn thẳng vào thu nhập thì bị lách trong tuần đầu: thưởng theo "tỉ lệ đúng hạn" thì người ta đặt deadline thật xa; thưởng theo "số task hoàn thành" thì người ta chia nhỏ task ra. Đây không phải chuyện đạo đức nhân viên — bất kỳ ai bị đo bằng một con số cũng sẽ tối ưu con số đó.

Giữ đúng nguyên tắc đã dùng cho chấm công: **hệ thống đo và bày ra, con người quyết định.** Điểm tự động sẽ là thông tin tham khảo cạnh mỗi người, không phải công thức tự chia tiền — và chỉ thêm sau khi công ty đã chia tay vài kỳ.

#### Một việc phải làm ngoài phần mềm

Điều 104 khoản 2 yêu cầu **quy chế thưởng do công ty ban hành và công bố công khai tại nơi làm việc**, sau khi tham khảo ý kiến tổ chức đại diện người lao động nếu có. Phần mềm thực thi quy chế, không thay thế được nó. Chưa có quy chế bằng văn bản thì mọi con số hệ thống tính ra đều thiếu căn cứ khi có tranh chấp.

Các điều luật dẫn ở trên nên nhờ kế toán hoặc pháp chế xác nhận lại.

#### Kết quả kiểm thử

| Cổng | Kết quả |
|---|---|
| Pest | ✅ **447 passed** (3918 assertions) |
| Pint · Larastan mức 8 · Deptrac | ✅ Sạch |
| ESLint / Prettier / `tsc` / `next build` | ✅ PASS |

Thêm **19 test** trong `tests/Feature/Http/Payroll/ProjectBonusTest.php`.

Đã **chạy thật qua HTTP**: lập quỹ 50.000.000; chia số âm → `422` kèm câu *"Số tiền thưởng không được âm. Muốn giảm phần của ai thì đặt số nhỏ hơn, kể cả 0."*; chia `12.500.000,10` → còn lại đúng `37.499.999,90`; trước khi chốt nhân viên thấy 0 khoản, sau khi chốt thấy đúng phần của mình kèm lý do; sửa sau khi chốt → `BONUS_POOL_NOT_EDITABLE`; nhân viên xem quỹ dự án → `403`. Và câu `UPDATE` gõ tay bỏ qua ứng dụng bị database chặn. Dữ liệu và token kiểm thử đã dọn.

#### Quyết định phát sinh

**Test kiến trúc bắt hai phương thức sai quy ước.** Tôi gộp `allocate()` và `changeStatus()` vào `ProjectBonusController`, nhưng dự án quy định thao tác ngoài CRUD phải là controller một hành động với `__invoke` — và bộ kiểm thử kiến trúc thực thi luật đó. Đã tách thành `AllocateBonusController` và `ChangeBonusPoolStatusController`. Đây là loại lỗi mà không có test kiến trúc thì trôi qua review dễ dàng, rồi vài đợt sau controller phình thành tám phương thức.

### Đợt 5 — Thưởng KPI & dashboard lãnh đạo

Tương tự phần trên: **phạt tiền là trái luật, nhưng giảm thưởng hiệu suất thì hợp lệ** vì thưởng là khoản có điều kiện. Nên module này thiết kế theo hướng *quỹ thưởng KPI có tăng có giảm*, không phải *thưởng và phạt*.

- [ ] Quỹ thưởng KPI theo dự án và theo cá nhân
- [ ] Tiêu chí cộng/trừ điểm gắn với dữ liệu thật: đúng hạn, chất lượng, đủ báo cáo
- [ ] Dashboard lãnh đạo: ai trễ deadline, ai chưa báo cáo, task quá hạn theo phòng ban, xu hướng đi muộn
- [ ] Báo cáo tháng tự tổng hợp, xuất PDF/Excel

---

### Quản lý phòng ban ✅ Đã xong

Trước đây thêm hoặc đổi tên một phòng ban phải sửa `OrganizationSeeder.php` rồi deploy lại — **cơ cấu tổ chức của công ty nằm trong mã nguồn**, và mỗi lần đụng vào nó là một lần đụng vào production. Dữ liệu đang chạy là năm phòng mẫu (KD, KT, MKT, HCNS, TCKT) cho một công ty dưới 10 người.

Giao diện ở `/settings/departments`, vào từ menu tài khoản.

#### Vì sao là quyền riêng, không dùng chung `user.manage`

Đây là quyết định quan trọng nhất của mục này. **Cây phòng ban là thứ quyết định ai nhìn thấy dữ liệu của ai.**

`Department::subtreeIds()` đỡ **13 chỗ**: chấm công, đơn nghỉ, đi muộn, báo cáo ngày, task của đội, danh sách người được giao việc. Chuyển một phòng ban sang nhánh khác đổi phạm vi nhìn của mọi trưởng phòng nằm trên đường đi — cùng lúc, không màn hình nào báo.

Thêm một nhân viên thì ảnh hưởng gói gọn ở một người. Đổi cây phòng ban thì ảnh hưởng cả hệ thống. Hai việc đó không đi chung một quyền được.

**Trưởng phòng KHÔNG có quyền này**, và có test khoá riêng: họ nhìn phòng mình cộng mọi phòng bên dưới, nên tự sửa được cây là tự nối thêm nhánh vào phạm vi của chính mình — gồm cả bảng công và đơn nghỉ của người phòng khác.

#### Vòng trong cây: lỗi treo không có log

`descendantIds()` duyệt cây bằng hàng đợi. Một vòng — A là cha của B rồi B được đặt làm cha của A — khiến hàng đợi không bao giờ rỗng: request treo tới hết timeout, php-fpm giữ nguyên tiến trình, **log không có một dòng nào**. Và vì hàm đó đỡ 13 chỗ nên chấm công, nghỉ phép, báo cáo chết cùng lúc.

Chặn ở hai lớp:

| Lớp | Ở đâu | Chặn gì |
|---|---|---|
| 1 | `UpdateDepartmentAction` | Không cho đặt cha là chính nó hoặc là cấp dưới của nó |
| 2 | `Department::descendantIds()` | Tập "đã thăm" — dừng ngay cả khi database đã sẵn một vòng |

Lớp 2 không thừa: vòng vẫn vào được bằng SQL sửa tay, bằng một migration sau này, hoặc bằng một lỗi ở đúng lớp 1. Có test ghi thẳng vào cột để dựng vòng, và test đó **đã được chứng minh là đỏ khi gỡ phanh ra** — không chỉ xanh khi có.

#### Xoá mềm làm hai ràng buộc khoá ngoại thành vô nghĩa

Migration khai `restrictOnDelete` cho `parent_id` và `nullOnDelete` cho `users.department_id`. Cả hai **chỉ có hiệu lực khi xoá cứng**. Phòng ban dùng xoá mềm, nên với database thì không có gì bị xoá — chỉ một cột ngày được điền — và cả hai ràng buộc đều im lặng.

Không chặn ở tầng ứng dụng thì xoá một phòng ban làm mọi phòng con và mọi nhân sự bên dưới **rơi khỏi cây** mà không có lỗi nào: `subtreeIds()` của phòng cấp trên không còn với tới họ, nên họ biến mất khỏi bảng công, khỏi danh sách đơn nghỉ, khỏi báo cáo của phòng. Người quản lý chỉ thấy màn hình ngắn đi.

Nên `DeleteDepartmentAction` từ chối khi còn phòng con hoặc còn nhân sự, kèm số lượng và cách xử lý. Đếm **cả người đã nghỉ việc** — họ vẫn giữ `department_id` để bảng công và lương cũ còn tra được theo phòng ban.

#### Tắt khác với xoá

`is_active = false` là cách ngừng dùng một phòng ban mà vẫn giữ mọi thứ:

- Biến mất khỏi các ô chọn, nên không ai xếp người mới vào
- **Vẫn nằm nguyên trong cây** với `subtreeIds()`

Vế thứ hai là chủ ý. Nếu tắt cũng đồng nghĩa với rơi khỏi cây thì "ngừng dùng một phòng ban" sẽ âm thầm giấu luôn nhân sự của nó khỏi màn hình của cấp trên — đúng loại hỏng im lặng mà cả mục này sinh ra để tránh.

#### Hai controller cho một tài nguyên

`DepartmentController` chỉ đọc và nằm trong danh sách miễn khai quyền của `ControllerAuthorizationTest`, với lý do viết ra: cơ cấu tổ chức là thông tin cả công ty vốn đã biết.

Phần ghi tách sang `DepartmentAdminController`. Không phải để ngăn nắp: thêm phương thức ghi vào tệp kia làm lý do miễn trừ thành lời nói dối, mà test chỉ dò xem tệp **có** dấu hiệu kiểm quyền hay không nên nó vẫn xanh. Hai tệp thì ranh giới công khai / quản trị nhìn thấy được ngay từ tên.

#### Chưa có

**Nhật ký kiểm toán cho thao tác trên cây.** `user_activities` khoá theo `user_id` nên không dùng lại được cho phòng ban; muốn có phải thêm bảng. Đáng làm, vì chuyển một phòng ban đổi phạm vi xem lương của người khác — nhưng nó là một quyết định riêng, không phải phần đuôi của mục này.

---

### Thanh bên chia theo vai ✅ Đã xong

Mười hai mục xếp phẳng không nói được mục nào là việc của nhân viên, mục nào là công cụ quản lý. Giám đốc mở ra thấy "Chấm công" nằm ngay cạnh "Nhân sự", không có gì gợi ý rằng một cái là bảng công của chính mình còn cái kia là quản trị cả công ty.

Chia làm ba nhóm theo **ai được phục vụ**, không theo chủ đề nghiệp vụ:

| Nhóm | Nghĩa |
|---|---|
| **Của tôi** | Ai cũng thấy, mở ra là dữ liệu của chính mình |
| **Quản lý** | Nhìn người khác: cả phòng, cả công ty |
| **Quản trị** | Sửa chính hệ thống: nhân sự, cơ cấu, cài đặt |

#### Nhân viên không thấy tiêu đề nhóm nào

Chỉ còn đúng một nhóm thì `visibleNavGroups` bỏ luôn tiêu đề. Một cái nhãn "Của tôi" phía trên danh sách mà mọi mục đều là của mình chỉ thêm một dòng chữ không phân biệt được gì với dòng nào cả.

#### Viên nhãn "cả đội" cho năm trang hai tầng

Năm trang — Báo cáo ngày, Chấm công, Nghỉ phép, Lương, Thưởng — có phần của chính mình ở trên và phần cả phòng ở dưới, phần dưới chỉ hiện với người có quyền. Nghĩa là **cùng một nhãn trên thanh bên dẫn tới hai màn hình khác nhau tuỳ người đang đăng nhập**, và đó chính là chỗ khó hình dung nhất.

Không tách thành hai mục riêng, vì chúng là MỘT trang: một mục trỏ tới giữa trang bằng neo sẽ làm trạng thái "đang mở" sai ở cả hai mục. Thay vào đó mục mang thêm viên nhãn `cả đội` với người có quyền. Người không có quyền không thấy gì thêm — với họ trang đó thật sự chỉ có một tầng.

#### Gom phần quản trị về một chỗ

Trước đây "Nhân sự" nằm trên thanh bên còn "Cơ cấu tổ chức" và "Cài đặt trang" giấu trong menu tài khoản. Giờ cả ba nằm trong nhóm **Quản trị**, và menu tài khoản chỉ còn những thứ thuộc về *chính người đang đăng nhập*: giao diện, cài đặt thông báo, đăng xuất.

Để chúng ở cả hai nơi thì câu "phần quản trị nằm ở đâu" có hai câu trả lời — đúng chỗ mập mờ mà việc gom nhóm sinh ra để bỏ.

#### Đã kiểm bằng quyền thật của cả bốn vai

Biên dịch `nav-items.ts` ra JS rồi chạy với đúng bộ quyền đọc từ database, thay vì nhìn mã rồi đoán. Kết quả:

```
NHÂN VIÊN      → 9 mục, KHÔNG có tiêu đề nhóm
TRƯỞNG PHÒNG   → Của tôi (3 mục có [cả đội]) · Quản lý (1)
GIÁM ĐỐC       → Của tôi (5 [cả đội]) · Quản lý (2) · Quản trị (2)
ADMIN          → như giám đốc, thêm Nhân sự
```

Chính phép kiểm này lộ ra hai điều về phân quyền, không phải về giao diện — xem "Câu hỏi còn mở".

---

## Quyết định kiến trúc đã chốt

**Có Redis ngay từ đầu.** Không phải vì hiệu năng mà vì queue: nén ảnh báo cáo (nhân viên chụp điện thoại 5–8MB mỗi ảnh, xử lý đồng bộ sẽ treo request), gửi thông báo, tổng hợp báo cáo, xuất Excel. Tiện thể dùng luôn cho cache, session, rate limit. Chi phí gần như bằng 0.

**Không có RabbitMQ.** Chỉ đáng giá khi có nhiều service nhiều ngôn ngữ, routing phức tạp, hoặc yêu cầu không mất message tuyệt đối. Dự án này là một monolith Laravel — thêm vào chỉ tốn công vận hành mà không đổi lại được gì.

**Không làm chat realtime riêng.** Công ty đã có Zalo/Slack. Comment trong task dùng polling 15–30 giây là đủ. Cần realtime thật thì thêm Laravel Reverb sau, không phải làm lại.

**Không làm app native.** Next.js dựng PWA, cài lên màn hình chính điện thoại, không cần lên store, không cần đội mobile riêng.

**Chấm công đo kết quả, không đo sự có mặt.** Xem lý do ở đợt 3.

**Hệ thống tự quản lý toàn bộ dữ liệu nhân sự.** Không đọc, không đồng bộ, không tham chiếu tới bất kỳ hệ thống nhân sự nào có sẵn. Bảng `users`, `departments`, `positions`, `teams` là của riêng hệ thống này và là nguồn sự thật duy nhất trong phạm vi của nó.

Kéo theo ba hệ quả cần nhớ:

- **Không có `UserDirectoryInterface`.** Không có nguồn dữ liệu ngoài thì không có ranh giới ngoài, nên cũng không có lý do tạo interface. `User` là model Eloquent bình thường trong `app/Domain/Identity/Models/`.
- **Danh sách nhân viên phải nhập vào lúc go-live** và phải được cập nhật khi có người vào/ra. Mục 1.3 có lệnh nhập từ Excel; quy trình duy trì về sau là việc của HR.
- **Đăng nhập là của riêng hệ thống này** — tài khoản, mật khẩu, phân quyền đều tự quản lý.

**Cũng không làm:** Gantt chart, tự động phân công task bằng AI, theo dõi màn hình nhân viên.

### Cố tình để lại sau đợt 1

Những thứ dưới đây có ích nhưng chưa cần, ghi ra để khỏi quên và để khỏi bàn lại:

| Tính năng | Vì sao để sau |
|---|---|
| **Task lặp lại định kỳ** | Hữu ích, nhưng thêm cả cơ chế sinh task theo lịch. Chờ xem thực tế có bao nhiêu việc lặp đã |
| **Task phụ thuộc** (A xong mới làm được B) | Đợt 1 đã có task con qua `parent_task_id`. Phụ thuộc chéo phức tạp hơn nhiều mà chưa chắc dùng tới |
| **Template task** | Chỉ đáng làm sau khi thấy mẫu việc lặp lại thật |
| **Uỷ quyền duyệt khi nghỉ phép** | Đợt 1 chưa có luồng duyệt thật sự. Cần từ đợt 4 khi có đơn từ |
| **Chấm giờ theo từng task (timesheet)** | Chỉ cần nếu muốn biết mỗi task tốn bao nhiêu giờ. Chờ nhu cầu thật |
| **Đăng nhập một lần (SSO) Google Workspace** | Phụ thuộc câu hỏi mở #2 |
| **Chế độ ngoại tuyến cho PWA** | Phức tạp và dễ sinh xung đột dữ liệu. Đợt 2 khi upload ảnh mới thật sự cần cơ chế thử lại |

---

## Câu hỏi còn mở

Cần chốt trước khi làm sâu vào đợt 1:

### 0. Hai điều lộ ra khi kiểm thanh bên theo vai

Không phải lỗi giao diện — lỗi ở bộ quyền, và chỉ nhìn thấy khi xếp mọi mục theo vai cạnh nhau.

**a) Giám đốc KHÔNG có `user.manage`.**

Nghĩa là nhóm Quản trị của giám đốc có "Cơ cấu tổ chức" và "Cài đặt trang" nhưng **không có "Nhân sự"**: họ sắp xếp được cây phòng ban, nhưng không thêm được người vào phòng ban vừa tạo, cũng không đổi được phòng ban của ai.

Đây là lựa chọn cố ý ở `Role::defaultPermissions()` — tách quản trị *hệ thống* (IT) khỏi quản trị *nghiệp vụ*. Nhưng với công ty dưới 10 người, người duy nhất mang vai admin thường cũng chính là giám đốc, nên ranh giới đó không mua được gì mà lại chặn đúng việc họ cần làm. **Cần chốt: có cấp `user.manage` cho giám đốc không.**

**b) `RolePermissionSeeder` có một điểm mù.**

Nó chỉ cấp cho vai trò đã tồn tại những quyền **vừa mới ra đời trong lượt chạy đó**. Trường hợp không xử lý được: một quyền đã có sẵn từ trước, sau này mới được thêm vào danh sách mặc định của một vai trò. Lúc đó quyền không còn "mới", nên **không vai trò nào nhận được nó, và không có gì báo**.

Đúng họ với lỗi đã cắn hai lần: *"tính năng có đủ, chỉ là không ai vào được."* Chưa cắn production — máy chủ được seed sau khi mã đã ổn định — nhưng database dev đã dính: trưởng phòng ở đó thiếu `report.view.team`, tức là mất đúng việc chính của họ mỗi sáng.

Cách sửa: đối chiếu quyền mặc định của vai trò với quyền vai trò đang có, và cấp thêm phần thiếu — thay vì chỉ nhìn vào danh sách quyền mới sinh.

### 1. Quy mô ✅ Đã chốt: **dưới 10 người**

Con số này gỡ bỏ khá nhiều lo lắng đã ghi rải rác trong tài liệu:

| Từng lo | Với dưới 10 người |
|---|---|
| Email vượt hạn mức Gmail SMTP | Không thành vấn đề. Gmail cho khoảng 500 thư/ngày; cả công ty gộp lại còn xa mức đó. **Chưa cần dịch vụ gửi thư chuyên dụng** |
| Nhịp tim chấm công ~96.000 lượt/ngày (ước cho 200 người) | Thực tế dưới **5.000 lượt/ngày**. Không cần tối ưu thêm |
| Bật `TWO_FACTOR_DRIVER=totp` khoá cả công ty | Dưới 10 người thì hướng dẫn từng người cài app xác thực là việc của một buổi chiều |
| Bảng công cả phòng sinh nhiều truy vấn | Lưới lớn nhất chỉ khoảng 10 × 31 ô |

**Nhưng đừng gỡ các mốc an toàn đã đặt.** Trần 100 dòng kèm tổng số, chỉ mục database, `lockForUpdate` — chúng rẻ, và chúng bảo vệ đúng lúc công ty tăng người chứ không phải lúc này.

### 2. Kênh thông báo

Công ty có Zalo OA không? Có dùng Google Workspace không (để cân nhắc đăng nhập một lần)?

### 3. Phần mềm lương hiện tại

Kế toán đang dùng gì (MISA, Fast, Excel)? Bảng công xuất ra cần đúng format nào?

### 4. Nhân viên onsite

Có bộ phận nào làm tại văn phòng không (kế toán, kho, lễ tân)? Nếu có thì cần chính sách chấm công riêng cho nhóm đó.

### 5. Hạ tầng triển khai

Chạy trên VPS trong nước, cloud nước ngoài, hay hạ tầng sẵn có của công ty? Ảnh hưởng tới độ trễ, chi phí, và nghĩa vụ lưu trữ dữ liệu cá nhân trong nước theo Nghị định 13/2023.

### 6. Nguồn lực và thời hạn

Mấy người làm dự án này, và có mốc thời gian nào phải kịp không? Nếu chỉ một người thì phạm vi đợt 1 nên cắt bớt — bảng Kanban và một phần giao diện có thể lùi lại.

### 7. Nhóm dùng thử đầu tiên

Phòng ban nào sẽ chạy thử trước? Nên chọn nhóm sẵn sàng góp ý, không nên mở toàn công ty ngay từ ngày đầu.

### 8. Dữ liệu hiện có

Task đang chạy dở ở Excel/Zalo có cần nhập vào hệ thống không, hay bắt đầu từ con số không kể từ ngày go-live?

Riêng **danh sách nhân viên và cơ cấu phòng ban** thì chắc chắn phải nhập, vì hệ thống tự quản lý nhân sự. Mục 1.3 có lệnh nhập từ Excel.

---

## Vận hành trên máy chủ thật

### `git pull` không bao giờ đủ

**Sau mọi lần `git pull` trên máy chủ, phải chạy `./scripts/deploy.sh`.**

Lý do: gần như không có gì trong repo được đọc trực tiếp lúc chạy. Script sao lưu, mã đã build của giao diện, autoload của PHP — tất cả **được nướng vào image lúc build**. Sửa file trên đĩa rồi khởi động lại container thì container vẫn chạy bản cũ nằm trong image.

Đã cắn **hai lần trong cùng một ngày**:

| Lần | Sửa gì | Pull xong, chạy lại | Vì sao |
|---|---|---|---|
| 1 | `docker/backup/backup.sh` — database rỗng bị coi là dump hỏng | Vẫn báo đúng câu lỗi cũ | `backup.sh` được COPY vào image; deploy chỉ build `app` và `frontend` |
| 2 | `frontend/src/lib/api-client.ts` — `buildUrl` ném `TypeError` | Đăng nhập vẫn hỏng y nguyên | Container vẫn chạy `explus/frontend:e3b6348`, bản vá nằm ở `550c218` |

Cả hai lần triệu chứng đều là **"tôi đã sửa rồi mà nó vẫn hỏng y hệt"** — không có thông báo lỗi nào, không có gì trong log, và không có gì gợi ý rằng mã đang chạy không phải mã vừa kéo về.

Cách tự kiểm trong 5 giây:

```bash
docker compose -f docker-compose.prod.yml images | grep explus
git rev-parse --short HEAD
```

Hai con số phải khớp. Không khớp nghĩa là chưa deploy.

### `bash -n` không bắt được lệnh không tồn tại

Một lần sửa file bằng công cụ thay chuỗi ghi ra `\n` **dạng hai ký tự** thay vì dấu xuống dòng, ngay giữa lệnh đổi container:

```bash
APP_IMAGE="$IMAGE" FRONTEND_IMAGE="$FRONTEND_IMAGE" \n    $COMPOSE up -d --no-deps app frontend nginx
```

Bash đọc `\n` là chữ `n` đã thoát — tức là **một lệnh tên `n`**. Cú pháp hợp lệ hoàn toàn, nên `bash -n` báo xanh. Chỉ lúc chạy thật mới ra `n: command not found`.

Điều tệ nhất là **vị trí** của nó: bước 4/5. Sao lưu xong, image build xong, migration chạy xong — rồi dừng. Container vẫn chạy image cũ, health check vẫn xanh, log vẫn sạch, và bản vá vừa build không hề được đưa vào dùng. Người vận hành thấy một dòng lỗi lạ ở cuối một màn hình đầy dấu tích.

`ShellScriptTest` khoá lại chuyện này: nó quét mọi `.sh` trong `scripts/` và `docker/`, và nó có một test riêng để chứng minh chính nó đang thật sự đọc được file — vì một máy dò tìm nhầm chỗ sẽ báo "sạch" y hệt một máy dò không tìm thấy gì.

### Kiểm tra sau mỗi lần deploy

Health check trong `deploy.sh` kiểm **cả API lẫn giao diện**, nhưng nó chạy từ trong máy chủ. Việc đó không thay được một lần mở trình duyệt thật:

- `buildUrl` ném lỗi ở phía trình duyệt thì **mọi lệnh `curl` từ máy chủ đều xanh** — không request nào rời khỏi tab Network để mà thấy.
- Đăng nhập hỏng theo kiểu đó hiện ra là "Không kết nối được tới máy chủ", trong khi máy chủ hoàn toàn bình thường.

Nên bước cuối của mỗi lần deploy là **mở `/login` bằng trình duyệt và đăng nhập một lần**.

---


## Đường ống CI/CD

```
đẩy lên main
   │
   ├─ Backend (PHP 8.4)   Pint · Larastan 8 · Deptrac · 688 test · composer audit
   ├─ Frontend (Node 24)  ESLint · Prettier · tsc · next build · npm audit
   │        └─ cả hai phải xanh mới đi tiếp
   │
   ├─ Build image → GHCR  ghcr.io/…/app:<sha>  ·  ghcr.io/…/frontend:<sha>
   │
   └─ Triển khai          SSH → ci-deploy.sh → deploy.sh --pull <sha>
              └─ kiểm https://extask.us/login từ INTERNET, không phải từ máy chủ
```

Pull request thì dừng sau hai cổng chặn. Chỉ `push` vào `main` mới build và triển khai.

### CI từng chạy 9 lần và đỏ cả 9

Đáng ghi lại vì nó là bài học chứ không phải sự cố: **một CI chưa bao giờ xanh là một CI không tồn tại.** Nó vẫn chạy, vẫn tốn phút, vẫn hiện dấu X mà không ai còn nhìn.

Cả bốn lỗi tìm ra đều cùng một hình dạng — *máy dev có sẵn thứ mà máy CI không có*:

| Cổng | Đỏ vì | Vì sao máy dev không thấy |
|---|---|---|
| `tsc` | `layout.tsx` dùng `LayoutProps<"/">`, kiểu do Next sinh vào `.next/types/` | Máy dev có sẵn từ lần build trước; CI chạy typecheck **trước** build |
| Larastan | `scriptsShell()` thiếu kiểu phần tử mảng | Cache 78MB trên máy dev che mất, chỉ lộ khi chạy lạnh |
| `npm audit` | `nanoid < 3.3.18` | Chưa bao giờ chạy tới — hai cổng trước đã đỏ |
| Pest | **358 test đỏ** | Chưa bao giờ chạy tới |

Lỗi thứ tư mới là lỗi đáng sợ nhất, và nó nằm ngay trong file CI:

```yaml
env:
  CACHE_STORE: redis        # ← dòng này làm đỏ 358 test
  QUEUE_CONNECTION: redis   # ← dòng này làm đỏ 10 test
```

Biến môi trường của tiến trình **luôn thắng** thẻ `<env>` của PHPUnit — `force` mặc định là `false`, nên PHPUnit không ghi đè biến đã có. Hai dòng đó lặng lẽ vứt bỏ lựa chọn đã cân nhắc trong `phpunit.xml`.

Cái đắt là `CACHE_STORE`: **bộ đếm giới hạn tần suất nằm trong cache**. Với `array` thì mỗi test có cache riêng và bộ đếm về 0; với `redis` thì bộ đếm sống xuyên qua mọi test và mọi tiến trình song song. Thông báo lỗi là:

```
Expected response status code [403] but received 429.
```

Không có chữ nào nhắc tới cache. Một test kiểm phân quyền báo sai vì bị chặn tần suất.

**Quy tắc rút ra: khối `env:` của CI chỉ được khai những gì phụ thuộc vào MÁY chạy test** — địa chỉ database, địa chỉ Redis. Mọi lựa chọn khác thuộc về `phpunit.xml`.

### Rà soát lỗ hổng: "có lỗ hổng" khác "không kiểm được"

`composer audit` và `npm audit` đều thoát khác 0 cho **cả hai** trường hợp. Bản đầu không phân biệt, nên một lần Packagist hay npm registry trục trặc là cả đường ống đỏ, `build-push` bị bỏ qua, và không deploy được — vì một lý do chẳng liên quan gì tới mã nguồn vừa đẩy lên. Đã xảy ra thật ở lần chạy đầu tiên sau khi khai đủ secret.

Giờ hai bước đó đọc JSON thay vì tin mã thoát:

| Kết quả | Xử lý |
|---|---|
| JSON hợp lệ, có khuyến cáo | **Chặn** — `::error::` kèm chi tiết |
| JSON hợp lệ, không có gì | Qua |
| Không phải JSON (mạng hỏng, registry lỗi) | `::warning::` rồi **đi tiếp** |

Vế thứ ba là vế cần giải thích. Một API không với tới được thì **không nói được gì** về mã nguồn — coi nó là "có lỗ hổng" cũng sai như coi nó là "sạch". Mà chặn deploy hàng giờ vì sự cố của bên thứ ba là cách nhanh nhất dạy người ta quen bỏ qua CI.

"Đi tiếp" ở đây không đồng nghĩa với im lặng: `::warning::` hiện ngay đầu trang tóm tắt của lần chạy, kèm nguyên văn stderr, và nói rõ *"đây là sự cố công cụ, không phải kết luận là sạch"*.

Đã kiểm bằng bốn tệp JSON giả trước khi đẩy — sạch, có khuyến cáo, rỗng, và rác — để chắc nhánh "chặn" thật sự chặn chứ không chỉ nhánh "qua" chạy được.

### Vì sao build trong CI chứ không trên máy chủ

Hai lý do, và lý do thứ hai mới là lý do thật:

1. Máy chủ lúc đo chỉ còn **~2,2GB khả dụng** trong khi `next build` cần ~2GB, và nó đang chạy chung với vài dự án khác. OOM killer không giết tiến trình build — nó giết tiến trình nào tiện tay nhất.
2. **Image build trên máy chủ không phải thứ CI đã kiểm.** CI kiểm mã nguồn rồi vứt đi; máy chủ build lại từ đầu bằng bộ phụ thuộc giải lại vào lúc khác. Không có gì đối chiếu hai thứ đó.

Với `--pull` thì thứ đang chạy đúng là thứ đã qua 688 test.

### Khoá SSH của CI không mở được shell

`authorized_keys` trên máy chủ trói khoá đó vào đúng một script:

```
command="/srv/explus/scripts/ci-deploy.sh",no-agent-forwarding,no-port-forwarding,no-pty,no-user-rc,no-X11-forwarding ssh-ed25519 AAAA… ci@explus
```

Gửi lệnh gì cũng không quan trọng — sshd luôn chạy `ci-deploy.sh`, còn lệnh người gọi gõ chỉ nằm trong `$SSH_ORIGINAL_COMMAND` cho script tự đọc và tự quyết. Script chỉ chấp nhận đúng dạng `deploy <sha 40 ký tự hệ 16>`.

Đã kiểm thật: đăng nhập bằng khoá đó rồi gõ `id; cat /srv/explus/.env` — không có gì chạy ngoài script.

Token GHCR đi qua **stdin**, không qua tham số dòng lệnh (tham số hiện trong `ps` cho mọi user trên máy đọc được), và là `GITHUB_TOKEN` của chính lần chạy đó nên hết hạn khi job kết thúc. Không có gì lâu dài phải cất trên máy chủ.

### Bốn secret cần khai trên GitHub

*Settings → Secrets and variables → Actions → New repository secret*

| Tên | Giá trị |
|---|---|
| `VPS_HOST` | Địa chỉ IP máy chủ |
| `VPS_USER` | `root` |
| `VPS_SSH_KEY` | Toàn bộ nội dung `~/.ssh/explus-ci-deploy` (khoá riêng, gồm cả hai dòng `BEGIN`/`END`) |
| `VPS_HOST_KEY` | Dòng `ssh-keyscan -t ed25519 <ip>` trả về — để ghim, không tin bừa lần đầu |

Thiếu bất kỳ cái nào thì job dừng ngay ở bước đầu kèm tên biến còn thiếu. **Không bỏ qua êm**: một job xanh vì nó không làm gì cả là thứ nguy hiểm nhất trong cả đường ống — nó dạy người đọc rằng dấu tích nghĩa là đã deploy.

### Quay lui

`deploy.sh` ghi **cả hai** image đang chạy vào `.last-known-good` trước khi đổi:

```
APP_IMAGE=ghcr.io/…/app:<sha>
FRONTEND_IMAGE=ghcr.io/…/frontend:<sha>
```

Bản trước chỉ ghi app, nên `rollback.sh` để lại hệ thống ở trạng thái lai — backend bản cũ, giao diện bản mới — và không có gì báo. Đúng hình dạng của lỗi đăng nhập đã mất một buổi để tìm.

`deploy.sh` giữ 3 bản image gần nhất và **không bao giờ xoá** bản ghi trong `.last-known-good` hay bản đang được container dùng.

---

## Ghi chú

- Nghỉ lễ Việt Nam đưa vào bảng dữ liệu, **không hardcode** — Tết âm lịch trôi theo từng năm.
- Mọi thao tác ảnh hưởng tiền lương phải có nhật ký kiểm toán không sửa được.
- Mọi cấu hình liên quan tới cách tính công/lương để trong database, không để trong code.
