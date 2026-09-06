<x-mail::message>
{{ __('blog.email_intro', ['store' => config('store.name')]) }}

# {{ $post->title }}

@if($imageUrl)
<img src="{{ $imageUrl }}" alt="{{ $post->title }}" style="width:100%;max-width:600px;height:auto;border-radius:12px;margin:8px 0 4px;">
@endif

@if($post->excerpt)
{{ $post->excerpt }}
@endif

<x-mail::button :url="$url">
{{ __('blog.email_read_cta') }}
</x-mail::button>

{{ __('blog.email_signoff', ['store' => config('store.name')]) }}

<x-slot:subcopy>
{{ __('blog.email_reason', ['store' => config('store.name')]) }}
@if($unsubscribeUrl)
[{{ __('blog.email_unsubscribe') }}]({{ $unsubscribeUrl }})
@endif
</x-slot:subcopy>
</x-mail::message>
