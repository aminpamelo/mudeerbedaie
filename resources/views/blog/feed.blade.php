<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">
    <channel>
        <title>{{ config('store.name') }} — {{ __('blog.nav_blog') }}</title>
        <link>{{ route('blog.index') }}</link>
        <description>{{ __('blog.index_meta', ['store' => config('store.name')]) }}</description>
        <language>{{ app()->getLocale() }}</language>
        <lastBuildDate>{{ $updatedAt->toRfc2822String() }}</lastBuildDate>
        <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml" />
@foreach($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ route('blog.show', $post->slug) }}</link>
            <guid isPermaLink="true">{{ route('blog.show', $post->slug) }}</guid>
            <pubDate>{{ $post->published_at?->toRfc2822String() }}</pubDate>
            <description>{{ $post->seo_description }}</description>
@if($post->category)
            <category>{{ $post->category->name }}</category>
@endif
            <content:encoded><![CDATA[{!! $post->content_html !!}]]></content:encoded>
        </item>
@endforeach
    </channel>
</rss>
