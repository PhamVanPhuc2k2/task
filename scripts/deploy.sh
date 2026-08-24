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
#   ./scripts/deploy.sh              # deploy từ mã nguồn hiện tại
#   ./scripts/deploy.sh v1.4.2       # deploy một tag cụ thể
#
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker-compose.prod.yml"
TAG="${1:-$(git rev-parse --short HEAD 2>/dev/null || date -u +%Y%m%d%H%M%S)}"
IMAGE="explus/app:${TAG}"
FRONTEND_IMAGE="explus/frontend:${TAG}"

echo "════════════════════════════════════════════"
echo "  Triển khai ${IMAGE}"
echo "             ${FRONTEND_IMAGE}"
echo "════════════════════════════════════════════"

# Ghi lại image đang chạy TRƯỚC khi đổi. Đây là thứ scripts/rollback.sh đọc.
DANG_CHAY=$($COMPOSE images app --format json 2>/dev/null \
    | grep -o '"Tag":"[^"]*"' | head -1 | cut -d'"' -f4 || echo "")

if [ -n "$DANG_CHAY" ]; then
    echo "explus/app:${DANG_CHAY}" > .last-known-good
    echo "→ Phiên bản đang chạy: ${DANG_CHAY} (đã ghi vào .last-known-good)"
fi

# ── 1. Sao lưu ───────────────────────────────────────
echo ""
echo "→ [1/5] Sao lưu database trước khi đổi gì..."
$COMPOSE run --rm backup once

# ── 2. Build ─────────────────────────────────────────
echo ""
echo "→ [2/5] Build image..."
APP_IMAGE="$IMAGE" $COMPOSE build app

# Frontend build riêng: nó là ứng dụng Node, không dùng chung tầng nào với PHP.
#
# Địa chỉ API được nhúng vào mã lúc build, và mặc định là đường dẫn TƯƠNG ĐỐI
# (`/api/v1`). Nhờ vậy ảnh build ra chạy được ở mọi tên miền — nginx đứng trước
# cả hai nên trình duyệt và API cùng một origin.
FRONTEND_IMAGE="$FRONTEND_IMAGE" $COMPOSE build frontend

# ── 3. Migration ─────────────────────────────────────
echo ""
echo "→ [3/5] Chạy migration (container tạm, chưa đổi gì đang chạy)..."
# Chạy trong container riêng chứ không đợi entrypoint của app: nếu migration
# hỏng, ta biết NGAY và hệ thống cũ vẫn đang phục vụ bình thường.
APP_IMAGE="$IMAGE" $COMPOSE run --rm \
    -e RUN_MIGRATIONS=false \
    app php artisan migrate --force --isolated --no-interaction

# Quyền mới phải tới được vai trò, nếu không tính năng vừa deploy sẽ 403 với
# đúng người đáng lẽ được dùng nó.
#
# Đã mắc thật khi thêm quyền `setting.manage`: migrate xong, mã mới chạy, trang
# cài đặt có đủ, nhưng giám đốc bấm vào thì 403 — và không có gì trong log nói
# vì sao. Deploy "thành công" mà tính năng không ai vào được.
#
# Chạy được nhiều lần: seeder CHỈ cấp thêm quyền vừa mới ra đời, không dùng
# `syncPermissions` nên không xoá tuỳ chỉnh của quản trị viên.
echo "   Đồng bộ quyền mới vào vai trò..."
APP_IMAGE="$IMAGE" $COMPOSE run --rm \
    -e RUN_MIGRATIONS=false \
    app php artisan db:seed --class=RolePermissionSeeder --force --no-interaction

# ── 4. Đổi web ───────────────────────────────────────
echo ""
echo "→ [4/5] Đổi container web..."
# Frontend lên TRƯỚC nginx: nginx chuyển tiếp sang nó, nên đổi nginx trước sẽ có
# một khoảng nó trỏ vào container đã chết.
APP_IMAGE="$IMAGE" FRONTEND_IMAGE="$FRONTEND_IMAGE"     $COMPOSE up -d --no-deps app frontend nginx

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
        echo "     Chạy ./scripts/rollback.sh để quay lui." >&2
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

echo ""
echo "✔ Xong. Đang chạy ${IMAGE}."
echo "  Quay lui: ./scripts/rollback.sh"
