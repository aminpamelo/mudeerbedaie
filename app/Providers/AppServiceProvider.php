<?php

namespace App\Providers;

use App\Listeners\BlockExampleEmails;
use App\Models\ClassModel;
use App\Models\LiveSession;
use App\Models\ProductOrder;
use App\Models\User;
use App\Observers\LiveSessionVerifiedObserver;
use App\Policies\LiveHostPolicy;
use App\Policies\ProductOrderPolicy;
use App\Services\Shipping\EasyParcelShippingService;
use App\Services\Shipping\JntShippingService;
use App\Services\Shipping\ShippingManager;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
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
            $manager->registerProvider($app->make(EasyParcelShippingService::class));

            return $manager;
        });

        // PHP 8.3 shim for Laravel MCP OAuth: swap in our ClientRepository that
        // adds Passport 13's createAuthorizationCodeGrantClient() on top of
        // Passport 12 (the newest Passport that supports PHP 8.3). Preserves
        // Passport's own personal-access-client constructor args.
        $this->app->singleton(\Laravel\Passport\ClientRepository::class, function ($app) {
            $config = $app->make('config')->get('passport.personal_access_client');

            return new \App\Passport\ClientRepository($config['id'] ?? null, $config['secret'] ?? null);
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

        // Funnel Studio MCP OAuth (Laravel MCP + Passport), shimmed for PHP 8.3.
        Passport::useClientModel(\App\Passport\Client::class);
        Passport::tokensCan(['mcp:use' => 'Operate your Funnel Studio account from an AI assistant']);
        // Consent screen shown when an AI assistant connects to the MCP server.
        Passport::authorizationView(fn ($parameters) => view('mcp.authorize', $parameters));

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

        Gate::define('manageUpsellCommissions', fn (User $user) => $user->hasRole('accountant') || $user->hasRole('admin'));

        Gate::policy(ProductOrder::class, ProductOrderPolicy::class);

        LiveSession::observe(LiveSessionVerifiedObserver::class);
    }
}
