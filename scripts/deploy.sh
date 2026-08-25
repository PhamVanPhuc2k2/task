#!/bin/bash
#
# Triển khai phiên bản mới.
#
# ── Nguyên tắc: mỗi bước phải quay lui được, và bước không quay lui được đứng
#    cuối cùng ─────────────────────────────────────────────────────────────────
#
# Thứ tự ở đây không tuỳ tiện:
#
#   1. Sao lưu       — có đường lùi trước khi động vào bất cứ thứ gì
#   2. Build image   — hỏng ở đây thì hệ thống cũ vẫn chạy nguyên
#   3. Migration     — bước KHÔNG quay lui được bằng cách đổi image
#   4. Đổi app       — nhanh, quay lui bằng cách trỏ lại tag cũ
#   5. Đổi worker    — sau cùng, vì nó chờ job đang chạy xong
#
# Bước 3 là bước đáng sợ. Migration chỉ thêm cột/bảng thì quay lui an toàn;
# migration **xoá hoặc đổi tên cột** thì image cũ không chạy được nữa. Quy ước
# của dự án: mọi migration phá vỡ tương thích phải tách làm hai lần deploy —
# lần một thêm cái mới và ghi cả hai chỗ, lần hai mới xoá cái cũ.
#
# Dùng:
#   ./scripts/deploy.sh              # build tại chỗ từ mã nguồn hiện tại
#   ./scripts/deploy.sh v1.4.2       # build tại chỗ, gắn tag đó
#   ./scripts/deploy.sh --pull abc123 # KÉO image đã build sẵn từ registry
#
# ── Vì sao có chế độ --pull ──────────────────────────────────────────────────
#
# Build ngay trên máy chủ đang phục vụ người dùng có hai vấn đề, và vấn đề thứ
# hai mới là vấn đề thật:
#
#   1. `next build` cần khoảng 2GB. Máy chủ có 7,5GB nhưng đang chạy chung với
#      vài dự án khác, lúc đo chỉ còn ~2,2GB khả dụng. Build lúc đó là đánh cược
#      với OOM killer — mà OOM killer không giết tiến trình build, nó giết tiến
#      trình nào tiện tay nhất.
#
#   2. **Image build trên máy chủ KHÔNG phải thứ CI đã kiểm.** CI kiểm mã nguồn
#      rồi vứt đi; máy chủ build lại từ đầu bằng bộ dependency giải lại lúc đó.
#      Hai lần giải `composer install`/`npm ci` cách nhau vài giờ có thể ra hai
#      cây phụ thuộc khác nhau, và không có gì đối chiếu.
#
# Với --pull thì thứ chạy trên máy chủ đúng là thứ CI đã build và đã chạy test.
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker-compose.prod.yml"

KEO_TU_REGISTRY=false
if [ "${1:-}" = "--pull" ]; then
    KEO_TU_REGISTRY=true
    shift
fi

# Đổi được bằng biến môi trường để không trói script vào một tài khoản GitHub.
REGISTRY="${REGISTRY:-ghcr.io/phamvanphuc2k2/task}"

TAG="${1:-$(git rev-parse --short HEAD 2>/dev/null || date -u +%Y%m%d%H%M%S)}"

if [ "$KEO_TU_REGISTRY" = true ]; then
    IMAGE="${REGISTRY}/app:${TAG}"
    FRONTEND_IMAGE="${REGISTRY}/frontend:${TAG}"
else
    IMAGE="explus/app:${TAG}"
    FRONTEND_IMAGE="explus/frontend:${TAG}"
fi

echo "════════════════════════════════════════════"
echo "  Triển khai ${IMAGE}"
echo "             ${FRONTEND_IMAGE}"
echo "════════════════════════════════════════════"

# Ghi lại image đang chạy TRƯỚC khi đổi. Đây là thứ scripts/rollback.sh đọc.
#
# Phải lấy THAM CHIẾU ĐẦY ĐỦ, không chỉ cái tag. Bản trước ghép cứng
# "explus/app:${TAG}", nên từ lúc image chuyển sang registry thì file
# .last-known-good trỏ vào một cái tên không tồn tại — và rollback.sh chỉ phát
# hiện ra điều đó vào đúng lúc đang cần quay lui gấp.
# Ghi CẢ HAI image. Bản trước chỉ ghi app, nên quay lui xong hệ thống ở trạng
# thái lai: backend bản cũ, giao diện bản mới. Không có gì báo, và đó đúng là
# hình dạng của lỗi đăng nhập đã mất một buổi để tìm.
anh_dang_chay() {
    local cid
    cid=$($COMPOSE ps -q "$1" 2>/dev/null | head -1 || echo "")
    [ -z "$cid" ] && return 0
    docker inspect --format '{{.Config.Image}}' "$cid" 2>/dev/null || true
}

APP_CU=$(anh_dang_chay app)
FE_CU=$(anh_dang_chay frontend)

if [ -n "$APP_CU" ]; then
    {
        echo "APP_IMAGE=${APP_CU}"
        [ -n "$FE_CU" ] && echo "FRONTEND_IMAGE=${FE_CU}"
    } > .last-known-good
    echo "→ Đang chạy: ${APP_CU}"
    [ -n "$FE_CU" ] && echo "           ${FE_CU}"
    echo "  (đã ghi vào .last-known-good)"
else
    echo "→ Chưa có container app nào chạy — lần deploy đầu tiên."
fi

# ── 1. Sao lưu ───────────────────────────────────────
echo ""
echo "→ [1/5] Sao lưu database trước khi đổi gì..."
# Build LẠI image backup trước khi chạy.
#
# `backup.sh` được COPY vào image lúc build, nên sửa file trên đĩa không có tác
# dụng gì cho tới khi build lại. Bản trước chỉ build `app` và `frontend`, nên
# mọi thay đổi trong script sao lưu **âm thầm không được áp dụng** — sửa xong,
# `git pull` xong, chạy lại vẫn ra hành vi cũ, và không có gì gợi ý vì sao.
#
# Gặp thật khi deploy lần đầu lên máy chủ: vá lỗi "database rỗng bị coi là dump
# hỏng", pull về, chạy lại vẫn báo đúng câu lỗi cũ.
#
# Build ở đây rẻ: Docker dùng lại cache khi Dockerfile và script không đổi.
$COMPOSE build backup
$COMPOSE run --rm backup once

# ── 2. Lấy image ─────────────────────────────────────
echo ""

if [ "$KEO_TU_REGISTRY" = true ]; then
    echo "→ [2/5] Kéo image từ registry (KHÔNG build trên máy chủ)..."
    echo "   ${IMAGE}"
    echo "   ${FRONTEND_IMAGE}"

    # Kéo TRƯỚC khi chạy migration. Registry hỏng, tag gõ sai, hay chưa đăng
    # nhập được thì phải biết ngay tại đây — lúc chưa động vào database.
    docker pull "$IMAGE"
    docker pull "$FRONTEND_IMAGE"
else
    echo "→ [2/5] Build image tại chỗ..."
    APP_IMAGE="$IMAGE" $COMPOSE build app

    # Frontend build riêng: nó là ứng dụng Node, không dùng chung tầng nào với
    # PHP.
    #
    # Địa chỉ API được nhúng vào mã lúc build, và mặc định là đường dẫn TƯƠNG
    # ĐỐI (`/api/v1`). Nhờ vậy ảnh build ra chạy được ở mọi tên miền — nginx
    # đứng trước cả hai nên trình duyệt và API cùng một origin.
    FRONTEND_IMAGE="$FRONTEND_IMAGE" $COMPOSE build frontend
fi

# ── 3. Migration ─────────────────────────────────────
echo ""
echo "→ [3/5] Chạy migration (container tạm, chưa đổi gì đang chạy)..."
# Chạy trong container riêng chứ không đợi entrypoint của app: nếu migration
# hỏng, ta biết NGAY và hệ thống cũ vẫn đang phục vụ bình thường.
APP_IMAGE="$IMAGE" $COMPOSE run --rm \
    -e RUN_MIGRATIONS=false \
    app php artisan migrate --force --isolated --no-interaction

# Chạy TRỌN BỘ seeder — hai lỗi thật, cùng một nguyên nhân.
#
# 1. Quyền mới không tới được vai trò. Thêm quyền `setting.manage` xong: migrate
#    chạy, mã mới lên, trang cài đặt có đủ — nhưng giám đốc bấm vào thì 403, và
#    không có gì trong log nói vì sao.
#
# 2. Không có tài khoản nào. Bản trước chỉ gọi RolePermissionSeeder, nên sau lần
#    deploy ĐẦU TIÊN database có 27 quyền và **0 người dùng** — bảy kiểm tra đều
#    xanh, chỉ là không có cửa vào.
#
# Cả hai đều là "deploy thành công, hệ thống không dùng được".
#
# DatabaseSeeder gọi ba seeder, và cả ba đều chạy lại được nhiều lần:
#   - OrganizationSeeder  : firstOrCreate phòng ban và chức vụ
#   - RolePermissionSeeder: chỉ cấp thêm quyền vừa ra đời, KHÔNG dùng
#                           syncPermissions nên không xoá tuỳ chỉnh
#   - AdminUserSeeder     : bỏ qua nếu tài khoản đã tồn tại
echo "   Đồng bộ quyền và tài khoản quản trị..."
APP_IMAGE="$IMAGE" $COMPOSE run --rm \
    -e RUN_MIGRATIONS=false \
    app php artisan db:seed --force --no-interaction

# ── 4. Đổi web ───────────────────────────────────────
echo ""
echo "→ [4/5] Đổi container web..."
# Frontend lên TRƯỚC nginx: nginx chuyển tiếp sang nó, nên đổi nginx trước sẽ có
# một khoảng nó trỏ vào container đã chết.
APP_IMAGE="$IMAGE" FRONTEND_IMAGE="$FRONTEND_IMAGE" \
    $COMPOSE up -d --no-deps app frontend nginx

# ── Nạp lại nginx. KHÔNG bỏ dòng này ─────────────────────────────────────────
#
# nginx phân giải `app` và `frontend` qua DNS nội bộ của Docker **một lần duy
# nhất, lúc nạp cấu hình**, rồi giữ nguyên địa chỉ IP đó.
#
# Dòng `up -d` ở trên tạo lại container app và frontend, và container mới
# thường — nhưng KHÔNG phải luôn luôn — nhận lại đúng IP cũ. Compose thấy cấu
# hình nginx không đổi nên để nguyên nó ("Container explus-nginx-1 Running").
# Khi IP đổi, nginx vẫn chuyển tiếp tới hai địa chỉ đã chết: **toàn bộ trang
# trả 502 trong khi cả sáu container đều báo healthy.**
#
# Đây không phải giả thuyết. Nó đã xảy ra ngày 25/08: app nhận .5, frontend
# nhận .4, nginx vẫn giữ cặp IP của lần trước, health check đỏ sau 60 giây, và
# extask.us trả 502 cho tới khi nạp lại nginx bằng tay.
#
# Ba lần deploy trước đó đều xanh — vì container tình cờ nhận lại đúng IP cũ.
# Nghĩa là mỗi lần deploy là một lần tung đồng xu, và không có gì trong log gợi
# ý điều đó.
#
# `nginx -s reload` phân giải lại DNS mà không rơi một request nào: tiến trình
# cũ phục vụ nốt việc đang dở rồi mới tắt. Rẻ hơn hẳn `restart`, vốn có một
# khoảng ngắn không ai phục vụ.
echo "   Nạp lại nginx để nó phân giải lại địa chỉ app và frontend..."
$COMPOSE exec -T nginx nginx -s reload || $COMPOSE restart nginx

echo "   Chờ health check..."
for i in $(seq 1 30); do
    # Kiểm CẢ HAI: API và giao diện.
    #
    # Chỉ kiểm API thì deploy vẫn báo thành công khi container frontend chết —
    # và người dùng mở trình duyệt ra thấy 502. Đúng loại "deploy xanh, hệ thống
    # hỏng" mà cả script này sinh ra để tránh.
    if curl -fsS "http://localhost:${APP_PORT:-8080}/api/v1/health" >/dev/null 2>&1 \
        && curl -fsS -o /dev/null "http://localhost:${APP_PORT:-8080}/login"; then
        echo "   ✔ API và giao diện đều sẵn sàng."
        break
    fi
    if [ "$i" -eq 30 ]; then
        echo "   ✘ Health check KHÔNG xanh sau 60 giây." >&2
        echo "" >&2
        # Nói rõ hệ thống đang ở trạng thái nào.
        #
        # Script dừng ở đây nên BƯỚC 5 CHƯA CHẠY: app và giao diện đã sang bản
        # mới, còn worker vẫn ở bản cũ. Không nói ra thì người đọc log tưởng
        # deploy chưa đụng gì cả, rồi mất thêm một lúc mới hiểu vì sao job chạy
        # nền vẫn hành xử theo mã cũ.
        echo "     Trạng thái hiện tại: app và giao diện ĐÃ sang ${TAG}," >&2
        echo "     worker vẫn ở bản cũ (bước 5 chưa chạy)." >&2
        echo "" >&2
        echo "     Quay lui:  ./scripts/rollback.sh" >&2
        echo "     Xem log:   $COMPOSE logs --tail=50 app nginx frontend" >&2
        exit 1
    fi
    sleep 2
done

# ── 5. Đổi worker ────────────────────────────────────
echo ""
echo "→ [5/5] Đổi worker (chờ job đang chạy xong)..."
# `queue:restart` bảo worker dừng êm sau job hiện tại. Không có bước này thì
# worker cũ chạy code cũ cho tới khi bị giết giữa chừng.
APP_IMAGE="$IMAGE" FRONTEND_IMAGE="$FRONTEND_IMAGE" \
    $COMPOSE exec -T app php artisan queue:restart || true
APP_IMAGE="$IMAGE" $COMPOSE up -d --no-deps worker

# ── Dọn image cũ ─────────────────────────────────────
#
# Mỗi lần deploy để lại một cặp image ~680MB và không xoá gì. Đĩa 50GB, đã dùng
# 23GB — cứ để vậy thì vài chục lần deploy nữa là đầy, và ổ đầy trên máy chạy
# MySQL không phải "hết chỗ" mà là hỏng dữ liệu.
#
# Giữ ba bản gần nhất, đủ để quay lui vài bước.
#
# Hai thứ TUYỆT ĐỐI không được xoá, và đó là toàn bộ phần khó của đoạn này:
#   - image các container đang dùng
#   - image ghi trong .last-known-good — xoá nó là vứt đường lùi đi, mà lại
#     không có gì báo cho tới đúng lúc cần quay lui
echo ""
echo "→ Dọn image cũ (giữ 3 bản gần nhất)..."

GIU=$(mktemp)
{
    docker ps -a --format '{{.Image}}'
    # .last-known-good ở dạng KEY=giá trị — chỉ lấy phần giá trị.
    [ -f .last-known-good ] && sed 's/^[A-Z_]*=//' .last-known-good
    echo "$IMAGE"
    echo "$FRONTEND_IMAGE"
} | sort -u > "$GIU"

for KHO in "${REGISTRY}/app" "${REGISTRY}/frontend" explus/app explus/frontend; do
    # `|| true` không phải cho đẹp.
    #
    # Script này chạy dưới `set -o pipefail`. Repo chưa có image nào thì
    # `docker images` không in gì, `grep` không khớp gì và thoát 1, cả pipeline
    # trả về 1 — và `set -e` giết script NGAY SAU KHI deploy đã thành công trọn
    # vẹn. Người vận hành thấy deploy "thất bại" và phản xạ là chạy rollback,
    # tức là quay lui một bản deploy hoàn toàn tốt.
    DANH_SACH=$(docker images "$KHO" --format '{{.Repository}}:{{.Tag}}' 2>/dev/null \
        | grep -v ':<none>$' || true)

    [ -z "$DANH_SACH" ] && continue

    # `docker images` xếp mới nhất trước, nên bỏ 3 dòng đầu là bỏ 3 bản mới nhất.
    echo "$DANH_SACH" | tail -n +4 | while read -r CU; do
        if grep -qxF "$CU" "$GIU"; then
            echo "   giữ ${CU} (đang dùng hoặc là đường lùi)"
            continue
        fi
        echo "   xoá ${CU}"
        docker rmi "$CU" >/dev/null 2>&1 || true
    done
done

rm -f "$GIU"

echo ""
echo "✔ Xong. Đang chạy ${IMAGE}."
echo "  Quay lui: ./scripts/rollback.sh"
