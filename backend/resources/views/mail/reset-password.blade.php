@component('mail::message')
# Đặt lại mật khẩu

Chào {{ $userName }},

Có người vừa yêu cầu đặt lại mật khẩu cho tài khoản Explus của bạn. Bấm nút bên
dưới để chọn mật khẩu mới.

@component('mail::button', ['url' => $actionUrl])
Đặt lại mật khẩu
@endcomponent

Đường dẫn này hết hạn sau **{{ $expireMinutes }} phút** và chỉ dùng được một lần.

@component('mail::subcopy')
**Không phải bạn yêu cầu?** Bỏ qua email này — mật khẩu hiện tại vẫn giữ nguyên
và không có gì thay đổi. Nếu nhận email kiểu này nhiều lần mà không phải do
bạn, báo cho quản trị hệ thống.

Nếu nút trên không bấm được, chép đường dẫn này vào trình duyệt:
[{{ $actionUrl }}]({{ $actionUrl }})
@endcomponent
@endcomponent
