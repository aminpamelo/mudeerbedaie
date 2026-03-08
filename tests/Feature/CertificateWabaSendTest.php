<?php

use App\Jobs\SendCertificateEmailJob;
use App\Jobs\SendCertificateWabaJob;
use App\Models\CertificateIssue;
use App\Models\ClassModel;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\MetaCloudProvider;
use App\Services\WhatsApp\WhatsAppManager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

test('SendCertificateWabaJob sends template with document header and body variables', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $student = Student::factory()->create(['phone' => '60123456789']);
    $issue = CertificateIssue::factory()->issued()->create([
        'student_id' => $student->id,
        'file_path' => 'certificates/test.pdf',
    ]);

    Storage::disk('public')->put('certificates/test.pdf', 'fake-pdf');

    $template = WhatsAppTemplate::create([
        'name' => 'certificate_delivery',
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'APPROVED',
        'components' => [
            ['type' => 'HEADER', 'format' => 'DOCUMENT'],
            ['type' => 'BODY', 'text' => 'Assalamualaikum {{1}}, Sijil anda ({{2}}) telah dikeluarkan.'],
        ],
        'variable_mappings' => [
            'body' => ['1' => 'student_name', '2' => 'certificate_name'],
        ],
    ]);

    $mockProvider = Mockery::mock(MetaCloudProvider::class);
    $mockProvider->shouldReceive('uploadMedia')
        ->once()
        ->andReturn(['success' => true, 'media_id' => 'media-123']);
    $mockProvider->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function ($phone, $templateName, $language, $components) {
            $header = $components[0] ?? null;
            $body = $components[1] ?? null;

            return $phone === '60123456789'
                && $templateName === 'certificate_delivery'
                && $language === 'ms'
                && count($components) === 2
                && $header['type'] === 'header'
                && $header['parameters'][0]['type'] === 'document'
                && $header['parameters'][0]['document']['id'] === 'media-123'
                && $body['type'] === 'body'
                && $body['parameters'][0]['type'] === 'text'
                && $body['parameters'][0]['text'] !== '';
        })
        ->andReturn(['success' => true, 'message_id' => 'wamid.test123']);

    $mockManager = Mockery::mock(WhatsAppManager::class);
    $mockManager->shouldReceive('metaProvider')->once()->andReturn($mockProvider);

    $mockWhatsApp = Mockery::mock(\App\Services\WhatsAppService::class);
    $mockWhatsApp->shouldReceive('storeOutboundMessage')->once();

    $job = new SendCertificateWabaJob(
        certificateIssueId: $issue->id,
        phoneNumber: '60123456789',
        templateId: $template->id,
        sentByUserId: $admin->id,
    );

    $job->handle($mockManager, $mockWhatsApp);

    expect($issue->fresh()->logs()->where('action', 'sent_waba')->exists())->toBeTrue();
});

test('SendCertificateWabaJob skips when template not approved', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = Student::factory()->create(['phone' => '60123456789']);
    $issue = CertificateIssue::factory()->issued()->create([
        'student_id' => $student->id,
        'file_path' => 'certificates/test.pdf',
    ]);

    $template = WhatsAppTemplate::create([
        'name' => 'certificate_pending',
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'PENDING',
        'components' => [],
    ]);

    $mockManager = Mockery::mock(WhatsAppManager::class);
    $mockManager->shouldNotReceive('metaProvider');

    $job = new SendCertificateWabaJob(
        certificateIssueId: $issue->id,
        phoneNumber: '60123456789',
        templateId: $template->id,
        sentByUserId: $admin->id,
    );

    $mockWhatsApp = Mockery::mock(\App\Services\WhatsAppService::class);

    $job->handle($mockManager, $mockWhatsApp);

    expect($issue->fresh()->logs()->where('action', 'sent_waba')->exists())->toBeFalse();
});

test('SendCertificateWabaJob skips when issue has no PDF', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $issue = CertificateIssue::factory()->issued()->create([
        'file_path' => null,
    ]);

    $template = WhatsAppTemplate::create([
        'name' => 'cert_no_pdf',
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'APPROVED',
        'components' => [],
    ]);

    $mockManager = Mockery::mock(WhatsAppManager::class);
    $mockManager->shouldNotReceive('metaProvider');
    $mockWhatsApp = Mockery::mock(\App\Services\WhatsAppService::class);

    $job = new SendCertificateWabaJob(
        certificateIssueId: $issue->id,
        phoneNumber: '60123456789',
        templateId: $template->id,
        sentByUserId: $admin->id,
    );

    $job->handle($mockManager, $mockWhatsApp);
});

test('send certificates via waba dispatches waba jobs', function () {
    Bus::fake([SendCertificateWabaJob::class]);
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $student = Student::factory()->create(['phone' => '60123456789']);
    $issue = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'file_path' => 'certificates/test.pdf',
    ]);

    Storage::disk('public')->put('certificates/test.pdf', 'fake-pdf');

    $template = WhatsAppTemplate::create([
        'name' => 'cert_waba_test',
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'APPROVED',
        'components' => [],
        'variable_mappings' => ['body' => ['1' => 'student_name']],
    ]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('sendIssueIds', [$issue->id])
        ->set('sendChannel', 'whatsapp')
        ->set('whatsappProvider', 'waba')
        ->set('selectedWabaTemplateId', $template->id)
        ->set('isBulkSend', false)
        ->call('sendCertificates')
        ->assertSet('showSendModal', false)
        ->assertDispatched('notify');

    Bus::assertDispatched(SendCertificateWabaJob::class, function ($job) use ($issue, $template) {
        return $job->certificateIssueId === $issue->id
            && $job->templateId === $template->id
            && $job->phoneNumber === '60123456789';
    });
});

test('send via both channel with waba dispatches email and waba jobs', function () {
    Bus::fake([SendCertificateEmailJob::class, SendCertificateWabaJob::class]);
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $student = Student::factory()->create(['phone' => '60123456789']);
    $issue = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'file_path' => 'certificates/test.pdf',
    ]);

    Storage::disk('public')->put('certificates/test.pdf', 'fake-pdf');

    $template = WhatsAppTemplate::create([
        'name' => 'cert_both_test',
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'APPROVED',
        'components' => [],
    ]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('sendIssueIds', [$issue->id])
        ->set('sendChannel', 'both')
        ->set('whatsappProvider', 'waba')
        ->set('selectedWabaTemplateId', $template->id)
        ->set('sendMessage', 'Here is your certificate. Congratulations!')
        ->set('isBulkSend', false)
        ->call('sendCertificates')
        ->assertSet('showSendModal', false);

    Bus::assertDispatched(SendCertificateEmailJob::class);
    Bus::assertDispatched(SendCertificateWabaJob::class);
});

test('close send modal resets waba state', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('whatsappProvider', 'waba')
        ->set('selectedWabaTemplateId', 99)
        ->call('closeSendModal')
        ->assertSet('whatsappProvider', 'onsend')
        ->assertSet('selectedWabaTemplateId', null);
});

test('send via waba requires template selection', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('sendIssueIds', [1])
        ->set('sendChannel', 'whatsapp')
        ->set('whatsappProvider', 'waba')
        ->set('selectedWabaTemplateId', null)
        ->call('sendCertificates')
        ->assertHasErrors(['selectedWabaTemplateId']);
});
