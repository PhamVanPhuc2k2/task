@component('mail::message')
# {{ $title }}

Chào {{ $userName }},

{{ $message }}

@component('mail::button', ['url' => $actionUrl])
Mở trong Explus
@endcomponent

@component('mail::subcopy')
Bạn nhận email này vì đang bật thông báo **{{ $settingsLabel }}**. Tắt trong
Explus tại mục *Cài đặt thông báo*.

Nếu nút trên không bấm được, chép đường dẫn này vào trình duyệt:
[{{ $actionUrl }}]({{ $actionUrl }})
@endcomponent
@endcomponent
