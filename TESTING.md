# Testing Guide

## Database Safety in Tests

**CRITICAL:** This project has multiple safety mechanisms to prevent accidental data loss during testing.

### How It Works

1. **In-Memory Database**: All tests run against an **in-memory SQLite database** (`:memory:`)
2. **Automatic Configuration**: The `TestCase` class forcefully overrides database settings
3. **Safety Checks**: Every test verifies it's using the correct database before running

### Test Configuration

**No `.env.testing` file needed!** The configuration is handled automatically by:

1. **phpunit.xml** - Sets environment variables for tests
2. **tests/TestCase.php** - Forces `:memory:` database and `testing` environment

### Running Tests

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/TikTokPackageImportTest.php

# Run specific test
./vendor/bin/pest --filter="package mapping creates order item"

# Run all TikTok import tests
./vendor/bin/pest tests/Feature/TikTok*
```

### What Happens During Tests

1. Test starts
2. `TestCase::createApplication()` forces:
   - `APP_ENV=testing`
   - `DB_CONNECTION=sqlite`
   - `DB_DATABASE=:memory:`
3. `TestCase::ensureTestingDatabase()` verifies configuration
4. If anything is wrong, test **FAILS IMMEDIATELY** with clear error message
5. Test runs in completely isolated in-memory database
6. Test finishes, memory database is destroyed
7. **Your main database is NEVER touched**

### Safety Mechanisms

#### 1. Forced Configuration (tests/TestCase.php)

```php
public function createApplication()
{
    // Force testing environment
    putenv('APP_ENV=testing');
    $_ENV['APP_ENV'] = 'testing';
    $_SERVER['APP_ENV'] = 'testing';

    $app = parent::createApplication();

    // Force in-memory database
    $app['config']->set('database.connections.sqlite.database', ':memory:');
    $app['config']->set('database.default', 'sqlite');

    return $app;
}
```

#### 2. Safety Verification

Every test checks:
- ✅ Database is `:memory:` (NOT a file path)
- ✅ Environment is `testing` (NOT local/production)

If ANY check fails:
- ❌ Test stops immediately
- ❌ Clear error message displayed
- ❌ No database operations performed

### Error Messages You Might See

#### Database Not Using :memory:

```
CRITICAL SAFETY ERROR: Tests must use ':memory:' database, not '/path/to/database.sqlite'.
This configuration should have been overridden in TestCase::createApplication().
Your production/development database is at risk!
```

**Solution**: This should never happen due to forced configuration. If you see this, contact the development team.

#### Not in Testing Environment

```
CRITICAL SAFETY ERROR: Tests must run in 'testing' environment, currently in 'local'.
This prevents accidental data loss in production databases.
```

**Solution**: This should never happen due to forced configuration. If you see this, contact the development team.

### Main Database Safety

Your main database at `database/database.sqlite`:
- ✅ **NEVER** accessed during tests
- ✅ **NEVER** modified during tests
- ✅ Protected by multiple safety layers
- ✅ Completely isolated from test environment

### Test Database Details

- **Type**: SQLite in-memory (`:memory:`)
- **Location**: RAM (not on disk)
- **Lifetime**: Created fresh for each test, destroyed after
- **Persistence**: None - data deleted immediately after test
- **Migration**: `RefreshDatabase` trait runs migrations for each test
- **Seeding**: Tests create their own data using factories

### Adding New Tests

When creating new tests, you don't need to worry about database configuration:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('example test', function () {
    // This automatically uses :memory: database
    // Database is fresh and empty
    // Create test data here
});
```

The safety mechanisms ensure you **cannot** accidentally use the main database.

### Troubleshooting

#### Tests Failing with Database Errors

1. Clear Laravel cache:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

2. Run tests again:
   ```bash
   ./vendor/bin/pest
   ```

#### Want to Check Configuration

```bash
# See what database tests would use (without running tests)
php artisan tinker
>>> app()->environment()
=> "local"  # This is GOOD - means tests haven't started

# During test, it will be "testing"
```

### Summary

✅ **Tests are safe** - Multiple protection layers
✅ **Main database protected** - Never touched by tests
✅ **Easy to use** - Just run `./vendor/bin/pest`
✅ **Fast** - In-memory database is very fast
✅ **Isolated** - Each test gets fresh database

**You cannot accidentally break production data with tests.**
