<?php

use App\Models\ClassNotificationSetting;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new
#[Layout('components.layouts.react-email-builder')]
class extends Component {
    public ClassNotificationSetting $setting;
    public string $initialDesign = '';
    public bool $showPreview = false;
    public string $previewHtml = '';
    public string $settingName = '';

    public function mount(int $settingId): void
    {
        $this->setting = ClassNotificationSetting::with('class')->findOrFail($settingId);

        // Get the type label for display
        $typeLabels = ClassNotificationSetting::getNotificationTypeLabels();
        $this->settingName = $typeLabels[$this->setting->notification_type]['name'] ?? $this->setting->notification_type;

        // Get design JSON for React editor
        if ($this->setting->design_json) {
            $this->initialDesign = json_encode($this->setting->design_json);
        }
    }

    public function saveDesign(string $designJson, string $html): void
    {
        $finalHtml = $this->compileEmailHtml($html);

        $this->setting->update([
            'design_json' => json_decode($designJson, true),
            'html_content' => $finalHtml,
            'editor_type' => 'visual',
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Templat berjaya disimpan!',
        ]);
    }

    public function autoSave(string $designJson, string $html): void
    {
        $finalHtml = $this->compileEmailHtml($html);

        $this->setting->update([
            'design_json' => json_decode($designJson, true),
            'html_content' => $finalHtml,
            'editor_type' => 'visual',
        ]);
    }

    public function previewEmailFromHtml(string $html): void
    {
        $sampleData = $this->getSampleData();

        foreach ($sampleData as $placeholder => $value) {
            $html = str_replace($placeholder, $value, $html);
        }

        $this->previewHtml = $html;
        $this->showPreview = true;
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
        $this->previewHtml = '';
    }

    public function sendTestEmail(string $email, string $html): void
    {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Alamat e-mel tidak sah');
        }

        // Replace placeholders with sample data
        $sampleData = $this->getSampleData();

        foreach ($sampleData as $placeholder => $value) {
            $html = str_replace($placeholder, $value, $html);
        }

        // Compile the full HTML
        $finalHtml = $this->compileEmailHtml($html);

        // Send the test email
        \Illuminate\Support\Facades\Mail::html($finalHtml, function ($message) use ($email) {
            $message->to($email)
                ->subject('[UJIAN] ' . $this->settingName . ' - ' . $this->setting->class->title);
        });
    }

    protected function getSampleData(): array
    {
        $class = $this->setting->class;

        return [
            '{{student_name}}' => 'Ahmad bin Abdullah',
            '{{teacher_name}}' => $class->teacher?->user?->name ?? 'Ustaz Muhammad',
            '{{class_name}}' => $class->title ?? 'Kelas Tajwid Asas',
            '{{course_name}}' => $class->course?->name ?? 'Kursus Al-Quran',
            '{{session_date}}' => now()->addDay()->format('d M Y'),
            '{{session_time}}' => '10:00 AM',
            '{{session_datetime}}' => now()->addDay()->format('d M Y, g:i A'),
            '{{location}}' => $class->location ?? 'Bilik 101, Bangunan A',
            '{{meeting_url}}' => $class->meeting_url ?? 'https://meet.google.com/abc-defg-hij',
            '{{whatsapp_link}}' => $class->whatsapp_group_link ?? 'https://chat.whatsapp.com/invite/abc123',
            '{{duration}}' => $class->formatted_duration ?? '2 jam',
            '{{remaining_sessions}}' => '8',
            '{{total_sessions}}' => (string) ($class->timetable?->total_sessions ?? '12'),
            '{{attendance_rate}}' => '85',
        ];
    }

    protected function compileEmailHtml(string $html): string
    {
        $title = $this->settingName . ' - ' . $this->setting->class->title;

        $emailHtml = <<<HTML
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{$title}</title>
    <style type="text/css">
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
    </style>
    <!--[if mso]>
    <style type="text/css">
        body, table, td {
            font-family: Arial, Helvetica, sans-serif !important;
        }
    </style>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4;">
    {$html}
</body>
</html>
HTML;

        return $emailHtml;
    }

    public function getBackUrl(): string
    {
        return route('classes.show', ['class' => $this->setting->class_id]) . '?tab=notifications';
    }
}
?>

<div wire:id="{{ $this->getId() }}" class="h-screen overflow-hidden">
    {{-- React Email Builder Container - wire:ignore prevents Livewire from re-rendering --}}
    <div
        wire:ignore
        id="react-email-builder-root"
        class="h-full"
        data-template-id="{{ $setting->id }}"
        data-template-name="{{ $settingName }} - {{ $setting->class->title }}"
        data-template-type="class_notification"
        data-template-language="ms"
        data-initial-design="{{ $initialDesign }}"
        data-back-url="{{ $this->getBackUrl() }}"
    ></div>

    {{-- Preview Modal (Livewire handled) --}}
    @if($showPreview)
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[300] flex items-center justify-center" wire:click.self="closePreview">
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-2xl w-[90%] max-w-[700px] max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-zinc-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Pratonton E-mel</h2>
                    <button type="button" wire:click="closePreview" class="p-2 hover:bg-gray-100 dark:hover:bg-zinc-700 rounded-lg transition-colors">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-auto p-6 bg-gray-100 dark:bg-zinc-700">
                    <div class="flex justify-center">
                        <iframe
                            srcdoc="{{ $previewHtml }}"
                            class="w-full max-w-[600px] h-[500px] border-0 bg-white shadow-lg rounded-lg"
                            sandbox="allow-same-origin"
                        ></iframe>
                    </div>
                </div>
                <div class="flex justify-end p-4 border-t border-gray-200 dark:border-zinc-700">
                    <button type="button" wire:click="closePreview" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-zinc-700 border border-gray-300 dark:border-zinc-600 rounded-lg hover:bg-gray-50 dark:hover:bg-zinc-600">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
