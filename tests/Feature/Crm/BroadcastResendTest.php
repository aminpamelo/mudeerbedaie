<?php

declare(strict_types=1);

use App\Jobs\SendBroadcastEmail;
use App\Models\Broadcast;
use App\Models\BroadcastLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function rsStudent(string $email, string $name = 'Client'): Student
{
    $user = User::factory()->create(['email' => $email, 'name' => $name]);

    return Student::factory()->create(['user_id' => $user->id]);
}

function rsBroadcast(array $attrs = []): Broadcast
{
    return Broadcast::create(array_merge([
        'name' => 'Resend Test',
        'type' => 'standard',
        'status' => 'sent',
        'from_name' => 'Sender',
        'from_email' => 'from@example.com',
        'subject' => 'Hello',
        'content' => 'Hi there.',
        'editor_type' => 'text',
        'total_recipients' => 0,
    ], $attrs));
}

/*
|--------------------------------------------------------------------------
| Job: targeted resend
|--------------------------------------------------------------------------
*/

it('resends only to the given recipients and leaves the campaign status alone', function () {
    Mail::fake();
    $s1 = rsStudent('a@gmail.com');
    $s2 = rsStudent('b@gmail.com');
    $b = rsBroadcast(['status' => 'sent', 'selected_students' => [$s1->id, $s2->id], 'total_recipients' => 2]);

    BroadcastLog::create(['broadcast_id' => $b->id, 'student_id' => $s1->id, 'email' => 'a@gmail.com', 'status' => 'failed', 'error_message' => 'boom']);
    BroadcastLog::create(['broadcast_id' => $b->id, 'student_id' => $s2->id, 'email' => 'b@gmail.com', 'status' => 'sent', 'sent_at' => Carbon::parse('2026-01-01 09:00:00')]);

    SendBroadcastEmail::dispatchSync($b, [$s1->id]);

    expect(BroadcastLog::where('student_id', $s1->id)->first()->status)->toBe('sent')      // failed → resent
        ->and(BroadcastLog::where('student_id', $s2->id)->first()->sent_at->toDateTimeString())->toBe('2026-01-01 09:00:00') // untouched
        ->and($b->fresh()->status)->toBe('sent'); // campaign status not flipped
});

it('never resends to a student outside the campaign audience', function () {
    Mail::fake();
    $inside = rsStudent('inside@gmail.com');
    $outside = rsStudent('outside@gmail.com');
    $b = rsBroadcast(['status' => 'sent', 'selected_students' => [$inside->id], 'total_recipients' => 1]);

    SendBroadcastEmail::dispatchSync($b, [$inside->id, $outside->id]);

    expect(BroadcastLog::where('student_id', $inside->id)->exists())->toBeTrue()
        ->and(BroadcastLog::where('student_id', $outside->id)->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Report tab (Volt): per-row + bulk resend actions
|--------------------------------------------------------------------------
*/

it('renders the report tab with a delivery breakdown and resend action', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $s1 = rsStudent('rina@gmail.com', 'Rina');
    $b = rsBroadcast(['status' => 'sent', 'selected_students' => [$s1->id], 'total_recipients' => 1]);
    BroadcastLog::create(['broadcast_id' => $b->id, 'student_id' => $s1->id, 'email' => 'rina@gmail.com', 'status' => 'failed', 'error_message' => 'x']);

    Volt::actingAs($admin)->test('crm.broadcast-show', ['broadcast' => $b])
        ->set('activeTab', 'report')
        ->assertSee('Delivery breakdown')
        ->assertSee('Rina')
        ->assertSee('Resend');
});

it('resends to a single recipient from the report', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $s1 = rsStudent('one@gmail.com');
    $b = rsBroadcast(['status' => 'sent', 'selected_students' => [$s1->id], 'total_recipients' => 1]);

    Volt::actingAs($admin)->test('crm.broadcast-show', ['broadcast' => $b])
        ->call('resendOne', $s1->id);

    Queue::assertPushed(SendBroadcastEmail::class, fn ($job) => $job->onlyStudentIds === [$s1->id]);
});

it('bulk-resends to the checked recipients', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $s1 = rsStudent('x@gmail.com');
    $s2 = rsStudent('y@gmail.com');
    $b = rsBroadcast(['status' => 'sent', 'selected_students' => [$s1->id, $s2->id], 'total_recipients' => 2]);

    Volt::actingAs($admin)->test('crm.broadcast-show', ['broadcast' => $b])
        ->set('selected', [(string) $s1->id, (string) $s2->id])
        ->call('resendSelected');

    Queue::assertPushed(SendBroadcastEmail::class, fn ($job) => $job->onlyStudentIds === [$s1->id, $s2->id]);
});

it('does nothing and warns when bulk-resending with no selection', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $b = rsBroadcast(['status' => 'sent', 'selected_students' => [], 'total_recipients' => 0]);

    Volt::actingAs($admin)->test('crm.broadcast-show', ['broadcast' => $b])
        ->call('resendSelected')
        ->assertSet('flash', 'Select at least one recipient to resend.');

    Queue::assertNotPushed(SendBroadcastEmail::class);
});

it('resends to everyone who failed', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $failed = rsStudent('failed@gmail.com');
    $ok = rsStudent('ok@gmail.com');
    $b = rsBroadcast(['status' => 'sent', 'selected_students' => [$failed->id, $ok->id], 'total_recipients' => 2]);
    BroadcastLog::create(['broadcast_id' => $b->id, 'student_id' => $failed->id, 'email' => 'failed@gmail.com', 'status' => 'failed', 'error_message' => 'x']);
    BroadcastLog::create(['broadcast_id' => $b->id, 'student_id' => $ok->id, 'email' => 'ok@gmail.com', 'status' => 'sent', 'sent_at' => now()]);

    Volt::actingAs($admin)->test('crm.broadcast-show', ['broadcast' => $b])
        ->call('resendFailed');

    Queue::assertPushed(SendBroadcastEmail::class, fn ($job) => $job->onlyStudentIds === [$failed->id]);
});

it('selects every recipient on the page in one click', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $s1 = rsStudent('p1@gmail.com');
    $s2 = rsStudent('p2@gmail.com');
    $b = rsBroadcast(['status' => 'sent', 'selected_students' => [$s1->id, $s2->id], 'total_recipients' => 2]);

    Volt::actingAs($admin)->test('crm.broadcast-show', ['broadcast' => $b])
        ->set('activeTab', 'report')
        ->call('toggleSelectPage')
        ->assertCount('selected', 2);
});
