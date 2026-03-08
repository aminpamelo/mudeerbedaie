<?php

use App\Jobs\SendCertificateEmailJob;
use App\Jobs\SendCertificateWhatsAppJob;
use App\Models\CertificateIssue;
use App\Models\ClassModel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

test('open send modal sets correct state for individual certificate', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $issue = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'file_path' => 'certificates/test.pdf',
    ]);

    Storage::disk('public')->put($issue->file_path, 'fake-pdf');

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->call('openSendModal', $issue->id)
        ->assertSet('showSendModal', true)
        ->assertSet('sendIssueIds', [$issue->id])
        ->assertSet('isBulkSend', false)
        ->assertSet('sendChannel', 'email')
        ->assertHasNoErrors();
});

test('open send modal fails for revoked certificate', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $issue = CertificateIssue::factory()->revoked()->create([
        'class_id' => $class->id,
    ]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->call('openSendModal', $issue->id)
        ->assertSet('showSendModal', false)
        ->assertDispatched('notify');
});

test('open send modal fails for certificate without PDF', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $issue = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'file_path' => null,
    ]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->call('openSendModal', $issue->id)
        ->assertSet('showSendModal', false)
        ->assertDispatched('notify');
});

test('open bulk send modal sets correct state for selected certificates', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $issues = CertificateIssue::factory()->issued()->count(3)->create([
        'class_id' => $class->id,
        'file_path' => 'certificates/test.pdf',
    ]);

    Storage::disk('public')->put('certificates/test.pdf', 'fake-pdf');

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', $issues->pluck('id')->map(fn ($id) => (string) $id)->toArray())
        ->call('openBulkSendModal')
        ->assertSet('showSendModal', true)
        ->assertSet('isBulkSend', true)
        ->assertSet('sendChannel', 'email')
        ->assertHasNoErrors();
});

test('bulk send modal filters out revoked certificates', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $issued = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'file_path' => 'certificates/test.pdf',
    ]);
    $revoked = CertificateIssue::factory()->revoked()->create([
        'class_id' => $class->id,
        'file_path' => 'certificates/test2.pdf',
    ]);

    Storage::disk('public')->put('certificates/test.pdf', 'fake-pdf');
    Storage::disk('public')->put('certificates/test2.pdf', 'fake-pdf');

    $component = Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [(string) $issued->id, (string) $revoked->id])
        ->call('openBulkSendModal');

    expect($component->get('sendIssueIds'))->toBe([$issued->id]);
});

test('send certificates via email dispatches email jobs', function () {
    Bus::fake([SendCertificateEmailJob::class]);
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $student = Student::factory()->create();
    $issue = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'file_path' => 'certificates/test.pdf',
    ]);

    Storage::disk('public')->put($issue->file_path, 'fake-pdf');

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('sendIssueIds', [$issue->id])
        ->set('sendChannel', 'email')
        ->set('sendMessage', 'Here is your certificate. Congratulations!')
        ->set('isBulkSend', false)
        ->call('sendCertificates')
        ->assertSet('showSendModal', false)
        ->assertDispatched('notify');

    Bus::assertDispatched(SendCertificateEmailJob::class, function ($job) use ($issue) {
        return $job->certificateIssueId === $issue->id;
    });
});

test('send certificates via whatsapp dispatches whatsapp jobs', function () {
    Bus::fake([SendCertificateWhatsAppJob::class]);
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $student = Student::factory()->create([
        'phone' => '60123456789',
    ]);
    $issue = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'file_path' => 'certificates/test.pdf',
    ]);

    Storage::disk('public')->put($issue->file_path, 'fake-pdf');

    // Mock WhatsApp as enabled
    $this->mock(\App\Services\WhatsAppService::class, function ($mock) {
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('getRandomDelay')->andReturn(10);
    });

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('sendIssueIds', [$issue->id])
        ->set('sendChannel', 'whatsapp')
        ->set('sendMessage', 'Here is your certificate. Congratulations!')
        ->set('isBulkSend', false)
        ->call('sendCertificates')
        ->assertSet('showSendModal', false)
        ->assertDispatched('notify');

    Bus::assertDispatched(SendCertificateWhatsAppJob::class, function ($job) use ($issue) {
        return $job->certificateIssueId === $issue->id
            && $job->phoneNumber === '60123456789';
    });
});

test('send certificates via both dispatches both jobs', function () {
    Bus::fake([SendCertificateEmailJob::class, SendCertificateWhatsAppJob::class]);
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $student = Student::factory()->create([
        'phone' => '60123456789',
    ]);
    $issue = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'file_path' => 'certificates/test.pdf',
    ]);

    Storage::disk('public')->put($issue->file_path, 'fake-pdf');

    $this->mock(\App\Services\WhatsAppService::class, function ($mock) {
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('getRandomDelay')->andReturn(10);
    });

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('sendIssueIds', [$issue->id])
        ->set('sendChannel', 'both')
        ->set('sendMessage', 'Here is your certificate. Congratulations!')
        ->set('isBulkSend', false)
        ->call('sendCertificates')
        ->assertSet('showSendModal', false)
        ->assertDispatched('notify');

    Bus::assertDispatched(SendCertificateEmailJob::class);
    Bus::assertDispatched(SendCertificateWhatsAppJob::class);
});

test('send skips students without email for email channel', function () {
    Bus::fake([SendCertificateEmailJob::class]);
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $user = User::factory()->create(['email' => null]);
    $student = Student::factory()->create(['user_id' => $user->id]);
    $issue = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'file_path' => 'certificates/test.pdf',
    ]);

    Storage::disk('public')->put($issue->file_path, 'fake-pdf');

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('sendIssueIds', [$issue->id])
        ->set('sendChannel', 'email')
        ->set('sendMessage', 'Here is your certificate. Congratulations!')
        ->call('sendCertificates')
        ->assertDispatched('notify');

    Bus::assertNotDispatched(SendCertificateEmailJob::class);
});

test('send skips students without phone for whatsapp channel', function () {
    Bus::fake([SendCertificateWhatsAppJob::class]);
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $user = User::factory()->create(['phone' => null]);
    $student = Student::factory()->create(['phone' => null, 'user_id' => $user->id]);
    $issue = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'file_path' => 'certificates/test.pdf',
    ]);

    Storage::disk('public')->put($issue->file_path, 'fake-pdf');

    $this->mock(\App\Services\WhatsAppService::class, function ($mock) {
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('getRandomDelay')->andReturn(10);
    });

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('sendIssueIds', [$issue->id])
        ->set('sendChannel', 'whatsapp')
        ->set('sendMessage', 'Here is your certificate. Congratulations!')
        ->call('sendCertificates')
        ->assertDispatched('notify');

    Bus::assertNotDispatched(SendCertificateWhatsAppJob::class);
});

test('send validates message minimum length', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('sendIssueIds', [1])
        ->set('sendChannel', 'email')
        ->set('sendMessage', 'short')
        ->call('sendCertificates')
        ->assertHasErrors(['sendMessage']);
});

test('close send modal resets state', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('showSendModal', true)
        ->set('sendIssueIds', [1, 2, 3])
        ->set('sendChannel', 'whatsapp')
        ->set('sendMessage', 'Test message')
        ->set('isBulkSend', true)
        ->call('closeSendModal')
        ->assertSet('showSendModal', false)
        ->assertSet('sendIssueIds', [])
        ->assertSet('sendChannel', 'email')
        ->assertSet('sendMessage', '')
        ->assertSet('isBulkSend', false)
        ->assertSet('whatsappProvider', 'onsend')
        ->assertSet('selectedWabaTemplateId', null);
});

test('bulk send with empty selection does nothing', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('selectedIssueIds', [])
        ->call('openBulkSendModal')
        ->assertSet('showSendModal', false)
        ->assertHasNoErrors();
});
