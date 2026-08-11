<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #0f172a; font-size: 12px; margin: 0; padding: 32px; }
        .header { border-bottom: 2px solid #4F46E5; padding-bottom: 12px; margin-bottom: 20px; }
        .title { font-size: 20px; font-weight: bold; color: #3730A3; margin: 0 0 4px; }
        .meta { color: #64748B; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 10px 12px; vertical-align: top; border-bottom: 1px solid #E2E8F0; }
        td.label { width: 38%; font-weight: bold; color: #334155; background: #F8FAFC; }
        td.value { color: #0f172a; }
        .empty { color: #94A3B8; font-style: italic; }
        .footer { margin-top: 24px; color: #94A3B8; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">{{ $form->title }}</p>
        <p class="meta">
            Submission #{{ $submission->id }}
            &middot; {{ $submission->created_at?->format('d M Y, H:i') }}
            @if ($submission->submitter)
                &middot; {{ $submission->submitter->name }}
            @else
                &middot; Awam
            @endif
        </p>
    </div>

    <table>
        @foreach ($answers as $answer)
            <tr>
                <td class="label">{{ $answer['label'] }}</td>
                <td class="value">
                    @if ($answer['type'] === 'file' && ! empty($answer['value']))
                        {{ $answer['value'] }} (fail dimuat naik)
                    @elseif ($answer['value'] === '' || $answer['value'] === null)
                        <span class="empty">—</span>
                    @else
                        {{ $answer['value'] }}
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    <p class="footer">Dijana dari {{ config('app.name') }} &middot; {{ now()->format('d M Y H:i') }}</p>
</body>
</html>
