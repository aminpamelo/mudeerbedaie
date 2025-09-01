# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel Livewire Starter Kit application built with Laravel 12, Livewire Volt, and Flux UI components. The project uses modern Laravel conventions with a focus on Livewire components for interactive functionality.

## Development Commands

### Setup and Installation
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies  
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Create SQLite database file
touch database/database.sqlite

# Run database migrations
php artisan migrate
```

### Development Server
```bash
# Start full development environment (recommended)
composer run dev
# This runs: server, queue worker, logs, and Vite dev server concurrently

# Or run individual services:
php artisan serve          # Laravel development server
npm run dev               # Vite development server for assets
php artisan queue:listen   # Queue worker
php artisan pail          # Real-time log viewer
```

### Building and Assets
```bash
npm run build            # Build assets for production
npm run dev             # Development asset watching
```

### Testing
```bash
composer run test       # Run full test suite (clears config first)
php artisan test        # Run tests directly
./vendor/bin/pest       # Run Pest tests directly
./vendor/bin/pest --filter=ExampleTest  # Run specific test
```

### Code Quality
```bash
./vendor/bin/pint       # Laravel Pint code formatting (equivalent to PHP-CS-Fixer)
./vendor/bin/pint --test # Check formatting without making changes
```

### Stripe Webhook Testing
```bash
# Stripe CLI setup and testing (requires Stripe CLI installation)
./install-stripe-cli.sh          # Install Stripe CLI automatically
./setup-stripe-webhook.sh         # Interactive setup wizard for webhooks
./webhook-test-commands.sh events # Show recent webhook events
./webhook-test-commands.sh config # Check webhook configuration

# Laravel webhook commands
php artisan webhook:test status   # Show webhook system status
php artisan webhook:test events   # Show recent webhook events  
php artisan webhook:test failed   # Show failed webhook events
php artisan webhook:test config   # Show detailed webhook configuration
php artisan webhook:test clean    # Clean old processed webhook events

# Stripe CLI webhook forwarding (after installation and login)
stripe listen --forward-to localhost:8000/stripe/webhook  # Forward webhooks locally
stripe trigger customer.updated   # Trigger test webhook events
stripe trigger invoice.payment_succeeded  # Test payment webhooks
```

## Architecture

### Framework Stack
- **Backend**: Laravel 12 with PHP 8.2+
- **Frontend**: Livewire Volt (single-file components) + Flux UI
- **CSS**: Tailwind CSS v4 via Vite plugin
- **Database**: SQLite (development), supports MySQL/PostgreSQL
- **Testing**: Pest PHP testing framework
- **Build Tool**: Vite with Laravel plugin

### Key Directories
- `app/Livewire/`: Traditional Livewire components and actions
- `resources/views/livewire/`: Volt single-file components (Blade + PHP logic)
- `resources/views/components/`: Reusable Blade components
- `resources/views/flux/`: Custom Flux UI component overrides
- `routes/web.php`: Web routes including Volt route definitions
- `tests/Feature/`: Feature tests for application workflows
- `tests/Unit/`: Unit tests for individual classes

### Livewire Volt Pattern
This project uses Livewire Volt, which allows single-file components with PHP logic at the top:

```php
<?php
// Component logic goes here
use Livewire\Volt\Component;
new class extends Component {
    public $property = '';
    public function method() { }
}
?>

<div>
    <!-- Blade template goes here -->
</div>
```

### Authentication & Settings
- Built-in authentication system with email verification
- Settings pages for profile, password, and appearance
- Uses Laravel's session-based authentication
- Logout action implemented as invokable class in `app/Livewire/Actions/Logout.php`

### Database Configuration
- Default: SQLite with `database/database.sqlite` file
- Configured for database sessions, cache, and queues
- Test environment uses in-memory SQLite (`:memory:`)

### Asset Pipeline
- Vite handles CSS/JS compilation with HMR
- Tailwind CSS v4 with Vite plugin (not PostCSS)
- Entry points: `resources/css/app.css`, `resources/js/app.js`
- Auto-refresh enabled for Blade template changes

### Testing Setup  
- Pest framework with Laravel plugin
- Feature tests use `RefreshDatabase` trait
- Separate test suites for Unit and Feature tests
- Custom expectation helper: `expect($value)->toBeOne()`




**For Access Roles**

Admin Roles
email: admin@example.com
Password: password

Teacher
Email: teacher@example.com
password: password

User
email: user@example.com
password: password

## Important Development Guidelines

### Flux UI Component Usage
- **Always use proper Flux UI components** instead of custom HTML for headers and layouts
- **Header spacing pattern**: Use the following structure for consistent admin page headers:
  ```html
  <div class="mb-6 flex items-center justify-between">
      <div>
          <flux:heading size="xl">Page Title</flux:heading>
          <flux:text class="mt-2">Page description</flux:text>
      </div>
      <flux:button variant="primary">Action Button</flux:button>
  </div>
  ```
- **Follow Flux UI principles**: Trust Flux UI's built-in spacing and avoid overriding with custom classes
- **Reference Context7 documentation** for proper Flux UI component usage when in doubt
- **Test with Playwright** to verify visual spacing and layout consistency

### Code Quality Standards
- **Use TodoWrite tool** for tracking multi-step tasks and progress
- **Test fixes visually** using Playwright browser automation
- **Follow Laravel conventions** and maintain consistency across admin pages
- **Document solutions** in CLAUDE.md to prevent recurring issues

### Volt Component Common Issues & Solutions

#### "Using $this when not in object context" Error
**Problem**: This error occurs when controller-based routes try to load Volt components as regular Blade views using `view()` helper.

**Root Cause**: Mismatch between route definition and component type:
```php
// ❌ WRONG: Controller route trying to load Volt component
Route::get('teachers', [TeacherController::class, 'index'])->name('teachers.index');
// In controller: return view('livewire.admin.teacher-list');
```

**Solution**: Use Volt routing for Volt components:
```php
// ✅ CORRECT: Volt route for Volt component
Volt::route('teachers', 'admin.teacher-list')->name('teachers.index');
```

**Quick Diagnosis**: 
1. Check if the error occurs on a page with Volt components
2. Verify route definition in `routes/web.php`
3. If using `Route::get()` with controller, convert to `Volt::route()`
4. Remove the controller method that returns `view()`

**Additional Notes**:
- Class-based Volt syntax is more reliable than functional API for complex components
- Always use `$this->property` to access computed properties in Volt templates
- Volt components should be routed directly, not through controllers



**Card For Testing**

Card number: 4242424242424242
MM/YY: 08/30
CVV: 819
Poscode: 15200



