# Container sao lưu — chỉ có đúng những gì cần để dump, mã hoá và phục hồi thử.
#
# ── Vì sao nền là chính image MySQL 8.4 ──────────────────────────────────────
#
# Bản đầu dùng Alpine cho nhẹ. Chạy thật thì lộ ra `mysql-client` của Alpine
# thực chất là client **MariaDB 10.11**, và nó không hiểu cả cờ
# `--set-gtid-purged` lẫn một số kiểu dữ liệu của MySQL 8.4. Bản dump ra 20
# byte — may là script có chốt kiểm kích thước nên nó không lặng lẽ ghi đè bản
# tốt bằng một file rỗng.
#
# Công cụ sao lưu là thứ cuối cùng nên đem đi tiết kiệm dung lượng: dùng đúng
# client cùng phiên bản với server thì loại hẳn một lớp rủi ro tương thích.
#
# ── Vì sao tách khỏi container ứng dụng ──────────────────────────────────────
#
# Nó chạy theo lịch, không phục vụ request, và là thứ DUY NHẤT được cầm khoá
# giải mã backup. Gộp vào `app` nghĩa là mọi lỗ hổng của ứng dụng web đều với
# tới được khoá đó.
FROM mysql:8.4

# `age` lấy từ bản phát hành chính thức — binary Go tĩnh, chạy được trên mọi
# distro. Không lấy từ kho gói của distro: bản Alpine link musl nên không chạy
# trên nền glibc này.
ARG AGE_VERSION=1.2.1

USER root

RUN microdnf install -y cronie tar gzip findutils \
    && microdnf clean all \
    && curl -fsSL "https://github.com/FiloSottile/age/releases/download/v${AGE_VERSION}/age-v${AGE_VERSION}-linux-amd64.tar.gz" \
        -o /tmp/age.tar.gz \
    && tar -xzf /tmp/age.tar.gz -C /tmp \
    && mv /tmp/age/age /tmp/age/age-keygen /usr/local/bin/ \
    && rm -rf /tmp/age /tmp/age.tar.gz \
    && age --version

COPY backup.sh /usr/local/bin/backup
COPY restore-drill.sh /usr/local/bin/restore-drill
COPY entrypoint.sh /usr/local/bin/entrypoint

# Bỏ ký tự xuống dòng kiểu Windows ngay trong lúc build.
#
# `.gitattributes` đã khai `*.sh eol=lf`, nhưng nó chỉ có tác dụng với file đi
# qua git. Máy dev Windows sửa file trực tiếp, hoặc một công cụ ghi lại file
# bằng CRLF, là script vào container với dòng `#!/bin/bash\r` — và Linux báo
# **"bad interpreter: No such file or directory"**, trỏ vào chính file vừa
# COPY, nên rất dễ đọc nhầm thành lỗi đường dẫn. Đã dính hai lần khi dựng
# container này.
#
# Chuẩn hoá ở đây thì host viết kiểu gì cũng không còn quan trọng.
RUN sed -i 's/\r$//' /usr/local/bin/backup \
                      /usr/local/bin/restore-drill \
                      /usr/local/bin/entrypoint \
    && chmod +x /usr/local/bin/backup \
                /usr/local/bin/restore-drill \
                /usr/local/bin/entrypoint

WORKDIR /backups

ENTRYPOINT ["/usr/local/bin/entrypoint"]
