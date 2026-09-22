<?php

declare(strict_types=1);

use App\Jobs\SendBroadcastEmail;
use App\Models\Audience;
use App\Models\Broadcast;
use App\Models\BroadcastLog;
use App\Models\Student;
use App\Models\User;
use App\Services\Broadcast\BroadcastTrackingInjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function bcStudent(string $email, string $name = 'Client'): Student
{
    $user = User::factory()->create(['email' => $email, 'name' => $name]);

    return Student::factory()->create(['user_id' => $user->id]);
}

function bcBroadcast(array $attrs = []): Broadcast
{
    return Broadcast::create(array_merge([
        'name' => 'Test Broadcast',
        'type' => 'standard',
        'status' => 'scheduled',
        'from_name' => 'Sender',
        'from_email' => 'from@example.com',
        'subject' => 'Hello {{name}}',
        'content' => 'Hi {{name}}, visit us.',
        'editor_type' => 'text',
        'total_recipients' => 0,
    ], $attrs));
}

/*
|--------------------------------------------------------------------------
| Scheduled sender command (future-only)
|--------------------------------------------------------------------------
*/

it('dispatches a scheduled broadcast whose time has arrived', function () {
    Queue::fake();
    $b = bcBroadcast(['status' => 'scheduled', 'scheduled_at' => now()->subMinutes(5)]);

    $this->artisan('broadcasts:send-scheduled')->assertSuccessful();

    Queue::assertPushed(SendBroadcastEmail::class);
    expect($b->fresh()->status)->toBe('sending');
});

it('skips scheduled broadcasts overdue beyond the grace window', function () {
    Queue::fake();
    $b = bcBroadcast(['status' => 'scheduled', 'scheduled_at' => now()->subDays(3)]);

    $this->artisan('broadcasts:send-scheduled')->assertSuccessful();

    Queue::assertNotPushed(SendBroadcastEmail::class);
    expect($b->fresh()->status)->toBe('scheduled');
});

it('does not dispatch future scheduled broadcasts', function () {
    Queue::fake();
    $b = bcBroadcast(['status' => 'scheduled', 'scheduled_at' => now()->addHour()]);

    $this->artisan('broadcasts:send-scheduled')->assertSuccessful();

    Queue::assertNotPushed(SendBroadcastEmail::class);
    expect($b->fresh()->status)->toBe('scheduled');
});

/*
|--------------------------------------------------------------------------
| Open / click tracking
|--------------------------------------------------------------------------
*/

it('records an open and returns a gif from the tracking pixel', function () {
    $b = bcBroadcast(['status' => 'sent']);
    $student = bcStudent('open@example.com');
    $log = BroadcastLog::create([
        'broadcast_id' => $b->id, 'student_id' => $student->id, 'email' => 'open@example.com', 'status' => 'sent',
    ]);

    $res = $this->get(URL::signedRoute('email.track.open', ['log' => $log->id]));

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('image/gif');
    expect($log->fresh()->opened_at)->not->toBeNull();
});

it('records a click and redirects to the original url', function () {
    $b = bcBroadcast(['status' => 'sent']);
    $student = bcStudent('click@example.com');
    $log = BroadcastLog::create([
        'broadcast_id' => $b->id, 'student_id' => $student->id, 'email' => 'click@example.com', 'status' => 'sent',
    ]);
    $target = 'https://example.com/promo';

    $res = $this->get(URL::signedRoute('email.track.click', ['log' => $log->id, 'u' => $target]));

    $res->assertRedirect($target);
    expect($log->fresh()->clicked_at)->not->toBeNull();
    expect($log->fresh()->opened_at)->not->toBeNull(); // a click implies an open
});

it('rejects an unsigned tracking request', function () {
    $b = bcBroadcast(['status' => 'sent']);
    $student = bcStudent('nosig@example.com');
    $log = BroadcastLog::create([
        'broadcast_id' => $b->id, 'student_id' => $student->id, 'email' => 'nosig@example.com', 'status' => 'sent',
    ]);

    $this->get(route('email.track.open', ['log' => $log->id]))->assertForbidden();
});

it('404s a click with a non-http target', function () {
    $b = bcBroadcast(['status' => 'sent']);
    $student = bcStudent('bad@example.com');
    $log = BroadcastLog::create([
        'broadcast_id' => $b->id, 'student_id' => $student->id, 'email' => 'bad@example.com', 'status' => 'sent',
    ]);

    $this->get(URL::signedRoute('email.track.click', ['log' => $log->id, 'u' => 'javascript:alert(1)']))
        ->assertNotFound();
    expect($log->fresh()->clicked_at)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Send job: pause/cancel/resume + tracking injection
|--------------------------------------------------------------------------
*/

it('does not send or log anything when the campaign is cancelled', function () {
    $student = bcStudent('cancel@example.com');
    $b = bcBroadcast(['status' => 'cancelled', 'selected_students' => [$student->id], 'total_recipients' => 1]);

    SendBroadcastEmail::dispatchSync($b);

    expect(BroadcastLog::count())->toBe(0);
    expect($b->fresh()->status)->toBe('cancelled');
});

it('is resume-safe and never re-processes an already-sent recipient', function () {
    $s1 = bcStudent('s1@example.com');
    $s2 = bcStudent('s2@example.com');
    $b = bcBroadcast(['status' => 'sending', 'selected_students' => [$s1->id, $s2->id], 'total_recipients' => 2]);

    // s1 delivered in a prior run — its log must be left completely untouched.
    BroadcastLog::create([
        'broadcast_id' => $b->id, 'student_id' => $s1->id, 'email' => 's1@example.com',
        'status' => 'sent', 'sent_at' => Carbon::parse('2026-01-01 10:00:00'),
    ]);

    SendBroadcastEmail::dispatchSync($b);

    expect(BroadcastLog::where('broadcast_id', $b->id)->count())->toBe(2)
        ->and(BroadcastLog::where('student_id', $s1->id)->count())->toBe(1) // no duplicate
        ->and(BroadcastLog::where('student_id', $s1->id)->first()->sent_at->toDateTimeString())->toBe('2026-01-01 10:00:00') // untouched
        ->and(BroadcastLog::where('student_id', $s2->id)->where('status', 'sent')->exists())->toBeTrue()
        ->and($b->fresh()->status)->toBe('sent');
});

it('injects open and click tracking into email html', function () {
    $b = bcBroadcast(['status' => 'sending']);
    $student = bcStudent('track@example.com');
    $log = BroadcastLog::create([
        'broadcast_id' => $b->id, 'student_id' => $student->id, 'email' => 'track@example.com', 'status' => 'pending',
    ]);

    $html = app(BroadcastTrackingInjector::class)
        ->inject('<html><body><a href="https://example.com/go">Go</a></body></html>', $log);

    expect($html)->toContain('email/track/open')                 // open pixel appended
        ->and($html)->toContain('email/track/click')             // link wrapped
        ->and($html)->not->toContain('href="https://example.com/go"'); // original link rewritten
});

/*
|--------------------------------------------------------------------------
| Broadcast detail page (Volt): audience count, recipient table, actions
|--------------------------------------------------------------------------
*/

it('shows real audience contact counts', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $audience = Audience::create(['name' => 'VIP List', 'status' => 'active']);
    $audience->students()->attach([bcStudent('a1@example.com')->id, bcStudent('a2@example.com')->id], ['subscribed_at' => now()]);

    $b = bcBroadcast(['status' => 'sent']);
    $b->audiences()->attach($audience->id);

    Volt::actingAs($admin)->test('crm.broadcast-show', ['broadcast' => $b])
        ->set('activeTab', 'overview')
        ->assertSee('VIP List')
        ->assertSee('2 contacts');
});

it('lists target recipients as queued before sending', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $s1 = bcStudent('rina@example.com', 'Rina');
    $b = bcBroadcast(['status' => 'scheduled', 'selected_students' => [$s1->id], 'total_recipients' => 1]);

    Volt::actingAs($admin)->test('crm.broadcast-show', ['broadcast' => $b])
        ->assertSee('Rina')
        ->assertSee('Queued');
});

it('pauses a sending campaign', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $b = bcBroadcast(['status' => 'sending']);

    Volt::actingAs($admin)->test('crm.broadcast-show', ['broadcast' => $b])->call('pause');

    expect($b->fresh()->status)->toBe('paused');
});

it('resumes a paused campaign and dispatches the send job', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $b = bcBroadcast(['status' => 'paused', 'selected_students' => []]);

    Volt::actingAs($admin)->test('crm.broadcast-show', ['broadcast' => $b])->call('resume');

    expect($b->fresh()->status)->toBe('sending');
    Queue::assertPushed(SendBroadcastEmail::class);
});

it('cancels a scheduled campaign from the detail page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $b = bcBroadcast(['status' => 'scheduled']);

    Volt::actingAs($admin)->test('crm.broadcast-show', ['broadcast' => $b])->call('cancel');

    expect($b->fresh()->status)->toBe('cancelled');
});

it('sends a scheduled campaign immediately via send now', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $b = bcBroadcast(['status' => 'scheduled', 'scheduled_at' => now()->addDay(), 'selected_students' => []]);

    Volt::actingAs($admin)->test('crm.broadcast-show', ['broadcast' => $b])->call('sendNow');

    $b->refresh();
    expect($b->status)->toBe('sending');
    expect($b->scheduled_at)->toBeNull();
    Queue::assertPushed(SendBroadcastEmail::class);
});
