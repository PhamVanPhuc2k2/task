<x-mail::layout>
{{-- Header --}}
{{--
    Logo trỏ về địa chỉ GIAO DIỆN, không phải `app.url`.

    `app.url` là địa chỉ API — bấm vào chỉ ra một trang JSON. Đây là mặc định
    của Laravel và đúng với ứng dụng một khối, nhưng dự án này tách backend và
    frontend thành hai địa chỉ.
--}}
<x-slot:header>
<x-mail::header :url="config('app.frontend_url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. Thư tự động, vui lòng không trả lời.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
