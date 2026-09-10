@php
    // The editable modal message is authored for WhatsApp (uses *bold* markers +
    // emojis). Strip the WA emphasis markers and keep line breaks for the email.
    $clean = trim(str_replace('*', '', (string) $body));
@endphp
<x-mail::message>
@if($clean !== '')
{!! nl2br(e($clean)) !!}
@else
# Pesanan Dihantar 📦

Salam {{ $order->getCustomerName() }},

Pesanan anda **{{ $order->order_number }}** telah pun dihantar. Anda kini boleh menjejak status penghantaran pakej anda menggunakan butang di bawah.
@endif

@if($trackingUrl)
<x-mail::button :url="$trackingUrl">
Jejak Pakej Anda
</x-mail::button>
@endif

<x-mail::panel>
**Butiran Penghantaran**<br>
No. Pesanan: {{ $order->order_number }}<br>
No. Tracking: {{ $trackingNumber ?: '—' }}<br>
Kurier: {{ $courier ?: '—' }}
</x-mail::panel>

Jika anda mempunyai sebarang pertanyaan tentang pesanan ini, sila balas e-mel ini — kami sedia membantu.

Terima kasih,<br>
Pasukan {{ $storeName }}

<x-slot:subcopy>
Jika butang "Jejak Pakej Anda" tidak berfungsi, salin dan tampal pautan berikut ke pelayar anda: <span class="break-all">{{ $trackingUrl ?: 'Tiada pautan jejak' }}</span>
</x-slot:subcopy>
</x-mail::message>
