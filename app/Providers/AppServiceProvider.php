<?php

namespace App\Providers;

use App\Listeners\BlockExampleEmails;
use App\Models\ClassModel;
use App\Models\User;
use App\Policies\LiveHostPolicy;
use App\Services\Shipping\JntShippingService;
use App\Services\Shipping\ShippingManager;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ShippingManager::class, function ($app) {
            $manager = new ShippingManager;
            $manager->registerProvider($app->make(JntShippingService::class));

            return $manager;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fix Livewire update route when domain-based routing adds a name prefix
        Livewire::setUpdateRoute(function ($handle) {
            return Route::post('/livewire/update', $handle)
                ->middleware('web');
        });

        Event::listen(MessageSending::class, BlockExampleEmails::class);

        Gate::define('manage-class', function (User $user, ClassModel $class) {
            // Admin can manage any class
            if ($user->isAdmin()) {
                return true;
            }

            // Teacher can manage their own classes
            if ($user->isTeacher() && $class->teacher_id === $user->teacher?->id) {
                return true;
            }

            // PIC can manage assigned classes
            return $user->isPicOf($class);
        });

        Gate::define('livehost.update', fn (User $actor, User $target) => (new LiveHostPolicy)->update($actor, $target));
        Gate::define('livehost.delete', fn (User $actor, User $target) => (new LiveHostPolicy)->delete($actor, $target));
    }
}
