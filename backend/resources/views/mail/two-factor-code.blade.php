@component('mail::message')
# Mã đăng nhập Explus

Chào {{ $userName }},

Dùng mã dưới đây để hoàn tất đăng nhập:

@component('mail::panel')
<div style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 30px; font-weight: 700; letter-spacing: 10px; text-align: center;">
{{ $code }}
</div>
@endcomponent

Mã có hiệu lực trong **{{ $expiresInMinutes }} phút** và chỉ dùng được một lần.

**Bạn không yêu cầu mã này?** Có người đang biết mật khẩu của bạn. Hãy đổi mật khẩu ngay và báo bộ phận kỹ thuật.

Explus không bao giờ hỏi mã này qua điện thoại hay tin nhắn.

@endcomponent
