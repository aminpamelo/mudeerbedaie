<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $template->name }} — Preview</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    @if ($template->custom_css)
        <style>{!! $template->custom_css !!}</style>
    @endif
</head>
<body class="bg-white font-sans antialiased">
    @if (empty($template->content['content'] ?? []))
        <div style="padding:80px 24px;text-align:center;color:#64748b;font-family:Inter,sans-serif;">
            This template is empty — apply it to a funnel step and design it in the editor.
        </div>
    @else
        {!! $renderedContent !!}
    @endif
</body>
</html>
