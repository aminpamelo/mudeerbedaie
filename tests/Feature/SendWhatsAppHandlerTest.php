<?php

declare(strict_types=1);

use App\Contracts\WhatsAppProviderInterface;
use App\Models\Student;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppManager;
use App\Services\Workflow\Actions\SendWhatsAppHandler;

it('sends whatsapp message via provider', function () {
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('isConfigured')->andReturn(true);
    $provider->shouldReceive('send')->once()->andReturn([
        'success' => true,
        'message_id' => 'msg-123',
        'message' => 'Message sent',
    ]);
    $provider->shouldReceive('getName')->andReturn('onsend');

    $manager = Mockery::mock(WhatsAppManager::class);
    $manager->shouldReceive('provider')->andReturn($provider);

    $user = User::factory()->create(['name' => 'Test Student']);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'phone' => '60123456789',
    ]);

    $handler = new SendWhatsAppHandler($manager);

    $result = $handler->execute($student, [
        'message' => 'Hello, your class is tomorrow!',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toBe('WhatsApp message sent successfully');
});

it('returns failure when no phone number', function () {
    $manager = Mockery::mock(WhatsAppManager::class);

    $user = User::factory()->create();
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'phone' => null,
    ]);

    $handler = new SendWhatsAppHandler($manager);

    $result = $handler->execute($student, [
        'message' => 'Test message',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('No phone number');
});

it('returns failure when message is empty', function () {
    $manager = Mockery::mock(WhatsAppManager::class);

    $user = User::factory()->create();
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'phone' => '60123456789',
    ]);

    $handler = new SendWhatsAppHandler($manager);

    $result = $handler->execute($student, []);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('required');
});

it('handles provider failure gracefully', function () {
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('isConfigured')->andReturn(true);
    $provider->shouldReceive('send')->once()->andReturn([
        'success' => false,
        'error' => 'Unauthorized',
    ]);
    $provider->shouldReceive('getName')->andReturn('onsend');

    $manager = Mockery::mock(WhatsAppManager::class);
    $manager->shouldReceive('provider')->andReturn($provider);

    $user = User::factory()->create();
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'phone' => '60123456789',
    ]);

    $handler = new SendWhatsAppHandler($manager);

    $result = $handler->execute($student, [
        'message' => 'Test message',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Failed');
});

it('succeeds when provider is not configured (development mode)', function () {
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('isConfigured')->andReturn(false);
    $provider->shouldReceive('getName')->andReturn('onsend');
    $provider->shouldNotReceive('send');

    $manager = Mockery::mock(WhatsAppManager::class);
    $manager->shouldReceive('provider')->andReturn($provider);

    $user = User::factory()->create();
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'phone' => '60123456789',
    ]);

    $handler = new SendWhatsAppHandler($manager);

    $result = $handler->execute($student, [
        'message' => 'Test message',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toBe('WhatsApp message sent successfully');
});

it('resolves merge tags in message', function () {
    $sentMessage = null;

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('isConfigured')->andReturn(true);
    $provider->shouldReceive('send')
        ->once()
        ->withArgs(function (string $phone, string $message) use (&$sentMessage) {
            $sentMessage = $message;

            return true;
        })
        ->andReturn([
            'success' => true,
            'message_id' => 'msg-456',
            'message' => 'Message sent',
        ]);
    $provider->shouldReceive('getName')->andReturn('onsend');

    $manager = Mockery::mock(WhatsAppManager::class);
    $manager->shouldReceive('provider')->andReturn($provider);

    $user = User::factory()->create(['name' => 'Ahmad Bin Ali']);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'phone' => '60123456789',
    ]);

    $handler = new SendWhatsAppHandler($manager);

    $result = $handler->execute($student, [
        'message' => 'Hello {{contact.name}}, welcome!',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($sentMessage)->toContain('Ahmad Bin Ali');
});

it('formats phone number correctly for Malaysian numbers', function () {
    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('isConfigured')->andReturn(true);
    $provider->shouldReceive('send')
        ->once()
        ->withArgs(function (string $phone) {
            return $phone === '60123456789';
        })
        ->andReturn([
            'success' => true,
            'message_id' => 'msg-789',
            'message' => 'Message sent',
        ]);
    $provider->shouldReceive('getName')->andReturn('onsend');

    $manager = Mockery::mock(WhatsAppManager::class);
    $manager->shouldReceive('provider')->andReturn($provider);

    $user = User::factory()->create();
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'phone' => '0123456789',
    ]);

    $handler = new SendWhatsAppHandler($manager);

    $result = $handler->execute($student, [
        'message' => 'Test message',
    ]);

    expect($result['success'])->toBeTrue();
});

it('can be resolved from the container', function () {
    $handler = app(SendWhatsAppHandler::class);

    expect($handler)->toBeInstanceOf(SendWhatsAppHandler::class);
});
