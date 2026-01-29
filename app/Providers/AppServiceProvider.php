<?php

namespace App\Providers;

use App\Models\ClassModel;
use App\Models\Task;
use App\Models\User;
use App\Observers\TaskObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Task Observer
        Task::observe(TaskObserver::class);

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
    }
}
